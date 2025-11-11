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

use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Utils;
use function array_push;
use function count;
use function is_string;

final class OverloadBuilder{

	/**
	 * @var Overload[]
	 * @phpstan-var list<Overload>
	 */
	private array $anonymousOverloads = [];

	/**
	 * @var Overload[]
	 * @phpstan-var array<string, Overload>
	 */
	private array $namedOverloads = [];

	/**
	 * @param Parameter[]|string[] $commonParameters
	 * @phpstan-param list<Parameter<*>|string> $commonParameters
	 */
	public function __construct(private array $commonParameters = []){}

	/**
	 * @param Parameter[]|string[] $commonParameters
	 * @phpstan-param list<Parameter<*>|string> $commonParameters
	 */
	public static function make(array $commonParameters = []) : self{
		return new self($commonParameters);
	}

	/**
	 * @param Parameter[]|string[] $uniqueParameters
	 * @param string|string[]      $permission
	 *
	 * @phpstan-param list<Parameter<*>|string> $uniqueParameters
	 * @phpstan-param string|list<string> $permission
	 * @phpstan-param anyClosure $handler
	 */
	public function executor(array $uniqueParameters, string|array $permission, \Closure $handler, bool $acceptsAliasUsed = false) : static{
		$mergedParameters = $this->commonParameters;
		array_push($mergedParameters, ...$uniqueParameters);
		$this->insertOverload(new ExecutorOverload($mergedParameters, $permission, $handler, acceptsAliasUsed: $acceptsAliasUsed), $uniqueParameters);
		return $this;
	}

	/**
	 * @phpstan-param list<Parameter<*>|string> $uniqueParameters
	 * @phpstan-param \Closure(OverloadBuilder) : (void|OverloadBuilder) $builderProcessor
	 */
	public function branch(array $uniqueParameters, \Closure $builderProcessor) : static{
		Utils::validateCallableSignature(function(OverloadBuilder $builder) : void{}, $builderProcessor);
		if(count($uniqueParameters) === 0){
			//no point making a sub branch for this - this allows us to maximise the effectiveness of named overloads
			$builderProcessor($this);
			return $this;
		}

		$args = $this->commonParameters;
		array_push($args, ...$uniqueParameters);
		$builder = new OverloadBuilder($args);
		$builderProcessor($builder);
		$this->insertOverload($builder->build(), $uniqueParameters);
		return $this;
	}

	/**
	 * @param Parameter[]|string[] $uniqueParameters
	 *
	 * @phpstan-param list<Parameter<*>|string> $uniqueParameters
	 */
	private function insertOverload(Overload $overload, array $uniqueParameters) : void{
		if(count($uniqueParameters) > 0 && is_string($uniqueParameters[0])){
			//If the first parameter of the overload is a literal, we can avoid a bruteforce lookup
			if(isset($this->namedOverloads[$uniqueParameters[0]])){
				throw new \InvalidArgumentException("Another overload already has the name " . $uniqueParameters[0] . ". Use a branch with the name as the common parameter if you want to overload it.");
			}
			$this->namedOverloads[$uniqueParameters[0]] = $overload;
		}else{
			//Otherwise, we're reliant on the overload's parameter parsing to decide which overload to call
			$this->anonymousOverloads[] = $overload;
		}
	}

	public function build() : Overload{
		return match(count($this->namedOverloads) + count($this->anonymousOverloads)){
			0 => throw new \LogicException("No overloads provided"),
			//no need to branch if there's only a single overload registered
			1 => $this->namedOverloads[0] ?? $this->anonymousOverloads[0] ?? throw new AssumptionFailedError(),
			default => new BranchingOverload($this->commonParameters, $this->anonymousOverloads, $this->namedOverloads)
		};
	}
}
