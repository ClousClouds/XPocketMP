<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\command\overload;

use DaveRandom\CallbackValidator\ParameterInfo;
use DaveRandom\CallbackValidator\Prototype;
use DaveRandom\CallbackValidator\ReturnInfo;
use DaveRandom\CallbackValidator\Type\BuiltInType;
use DaveRandom\CallbackValidator\Type\NamedType;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\Translatable;
use pocketmine\permission\PermissionManager;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Utils;
use function array_filter;
use function array_map;
use function count;
use function get_class;
use function implode;
use function is_string;
use function preg_match;
use function strlen;
use function strpos;
use function substr;
use function var_dump;

final class CommandOverload{

	private int $requiredInputCount;

	/**
	 * @var string[]
	 * @phpstan-var list<string>
	 */
	private array $permissions;

	/**
	 * @param Parameter[]|string[] $parameters
	 * @param string|string[]      $permission
	 * @phpstan-param list<Parameter<*>|string> $parameters
	 * @phpstan-param string|list<string>       $permission
	 * @phpstan-param anyClosure                $handler
	 */
	public function __construct(
		private array $parameters,
		string|array $permission,
		private \Closure $handler,
		private bool $acceptsAliasUsed = false
	){
		$permissions = is_string($permission) ? [$permission] : $permission;
		if(count($permissions) === 0){
			throw new \InvalidArgumentException("At least one permission must be provided");
		}
		$permissionManager = PermissionManager::getInstance();
		foreach($permissions as $perm){
			if($permissionManager->getPermission($perm) === null){
				throw new \InvalidArgumentException("Cannot use non-existing permission \"$perm\"");
			}
		}
		$this->permissions = $permissions;

		//TODO: auto infer parameter infos if they aren't provided?
		//TODO: allow the type of CommandSender to be constrained - this can be useful for player-only commands etc
		$alwaysPresentArgs = self::alwaysPresentArgs($this->acceptsAliasUsed);

		$passableParameters = array_filter($this->parameters, is_object(...));
		$expectedPrototype = new Prototype(
			new ReturnInfo(new NamedType(BuiltInType::VOID), byReference: false),
			...$alwaysPresentArgs,
			...array_map(fn(Parameter $p) => new ParameterInfo(
				$p->getCodeName(),
				$p->getCodeType(),
				byReference: false,
				isOptional: false,
				isVariadic: false,
			), $passableParameters)
		);
		$actualPrototype = Prototype::fromClosure($this->handler);
		Utils::validateCallableSignature($expectedPrototype, $actualPrototype);

		$this->requiredInputCount = $actualPrototype->getRequiredParameterCount() - count($alwaysPresentArgs);
	}

	/**
	 * @return ParameterInfo[]
	 */
	private static function alwaysPresentArgs(bool $acceptsAliasUsed) : array{
		$result = [new ParameterInfo("sender", new NamedType(CommandSender::class), byReference: false, isOptional: false, isVariadic: false)];
		if($acceptsAliasUsed){
			$result[] = new ParameterInfo("aliasUsed", new NamedType(BuiltInType::STRING), byReference: false, isOptional: false, isVariadic: false);
		}
		return $result;
	}

	/**
	 * @return string[]
	 * @phpstan-return list<string>
	 */
	public function getPermissions() : array{ return $this->permissions; }

	public function senderHasAnyPermissions(CommandSender $sender) : bool{
		foreach($this->permissions as $permission){
			if($sender->hasPermission($permission)){
				return true;
			}
		}

		return false;
	}

	public function getUsage() : Translatable{
		$templates = [];
		$args = [];
		$pos = 0;
		foreach($this->parameters as $parameter){
			if(is_string($parameter)){
				//literal token
				$templates[] = $parameter;
				continue;
			}
			//TODO: printable type info would be nice
			if($pos < $this->requiredInputCount){
				$template = "<{%$pos}>";
			}else{
				$template = "[{%$pos}]";
			}
			$suffix = $parameter->getSuffix();
			$template .= $suffix;
			$templates[] = $template;

			$args[] = $parameter->getPrintableName();
			$pos++;
		}

		return new Translatable(implode(" ", $templates), $args);
	}

	private static function skipWhitespace(string $commandLine, int &$offset) : int{
		if(preg_match('/\G\s+/', $commandLine, $matches, offset: $offset) > 0){
			$offset += strlen($matches[0]);
			return strlen($matches[0]);
		}
		return 0;
	}

	/**
	 * @throws InvalidCommandSyntaxException
	 */
	public function invoke(CommandSender $sender, string $aliasUsed, string $commandLine) : void{
		$offset = 0;
		$args = [];
		//skip preceding whitespace

		self::skipWhitespace($commandLine, $offset);
		if($offset < strlen($commandLine)){
			foreach($this->parameters as $parameter){
				if(is_string($parameter)){
					if(strpos($commandLine, $parameter, $offset) === $offset){
						$offset += strlen($parameter);
					}else{
						throw new ParameterParseException("Literal \"$parameter\" expected");
					}
				}else{
					try{
						$args[] = $parameter->parse($commandLine, $offset);
					}catch(ParameterParseException $e){
						throw new ParameterParseException(
							"Failed parsing argument \$" . $parameter->getCodeName() . ": " . $e->getMessage(),
							previous: $e
						);
					}
				}
				if(self::skipWhitespace($commandLine, $offset) === 0){
					if($offset === strlen($commandLine)){
						//no more tokens, rest of the parameters must be optional
						break;
					}else{
						if(is_string($parameter)){
							throw new AssumptionFailedError();
						}
						var_dump(substr($commandLine, $offset));
						throw new ParameterParseException("Parameter " . get_class($parameter) . " for \$" . $parameter->getCodeName() . " didn't stop on a whitespace character");
					}
				}
			}
		}
		if($offset !== strlen($commandLine)){
			throw new InvalidCommandSyntaxException("Too many arguments provided for overload");
		}
		if(count($args) < $this->requiredInputCount){
			throw new InvalidCommandSyntaxException("Not enough arguments provided for overload");
		}
		//Reflection magic here :)
		//TODO: maybe we don't want to invoke this directly, but hand the args back to the caller?
		//this would allow resolving by more than just overload order
		if($this->acceptsAliasUsed){
			// @phpstan-ignore-next-line
			($this->handler)($sender, $aliasUsed, ...$args);
		}else{
			// @phpstan-ignore-next-line
			($this->handler)($sender, ...$args);
		}
	}
}
