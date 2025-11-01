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

/**
 * Command handling related classes
 */
namespace pocketmine\command;

use pocketmine\command\overload\CommandOverload;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\lang\Translatable;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\BroadcastLoggerForwarder;
use pocketmine\utils\TextFormat;
use function array_unique;
use function count;
use function implode;
use function str_replace;
use function strtolower;
use function trim;

class Command{
	private readonly string $namespace;
	private readonly string $name;

	private Translatable|string|null $permissionMessage = null;

	/**
	 * @param CommandOverload[] $overloads
	 * @phpstan-param list<CommandOverload> $overloads
	 */
	public function __construct(
		string $namespace,
		string $name,
		private array $overloads,
		private Translatable|string $description = "",
	){
		if(count($this->overloads) === 0){
			throw new \InvalidArgumentException("At least one overload must be provided (extend LegacyCommand for classic execute())");
		}
		if($namespace === ""){
			throw new \InvalidArgumentException("Command namespace cannot be empty (set it to, for example, your plugin's name)");
		}
		if($name === ""){
			throw new \InvalidArgumentException("Command name cannot be empty");
		}
		$this->namespace = strtolower(trim($namespace));
		//TODO: case handling inconsistency preserved from old code
		$this->name = trim($name);
	}

	final public function executeOverloaded(CommandSender $sender, string $aliasUsed, string $rawArgs) : bool{
		foreach($this->overloads as $k => $overload){
			if(!$overload->senderHasAnyPermissions($sender)){
				continue;
			}
			try{
				$overload->invoke($sender, $aliasUsed, $rawArgs);
				return true;
			}catch(InvalidCommandSyntaxException $e){
				\GlobalLogger::get()->debug("Overload $k of /$aliasUsed rejected: " . $e->getMessage());
			}
		}

		$usages = $this->getUsages($sender, $aliasUsed);
		if(count($usages) === 0){
			$message = $this->permissionMessage ?? KnownTranslationFactory::pocketmine_command_error_permission($aliasUsed);
			if($message instanceof Translatable){
				$sender->sendMessage($message->prefix(TextFormat::RED));
			}elseif($message !== ""){
				$permissions = [];
				foreach($this->overloads as $overload){
					foreach($overload->getPermissions() as $permission){
						$permissions[] = $permission;
					}
				}
				$permissions = array_unique($permissions);
				$sender->sendMessage(str_replace("<permission>", implode(";", $permissions), $message));
			}
			return false;
		}

		foreach($usages as $usageMessage){
			$sender->sendMessage($sender->getLanguage()->translate(KnownTranslationFactory::commands_generic_usage($usageMessage)));
		}
		return false;
	}

	/**
	 * @return CommandOverload[]
	 * @phpstan-return list<CommandOverload>
	 */
	public function getOverloads() : array{ return $this->overloads; }

	/**
	 * @return CommandOverload[]
	 * @phpstan-return list<CommandOverload>
	 */
	public function getPermittedOverloads(CommandSender $sender) : array{
		$overloads = [];
		foreach($this->overloads as $overload){
			if($overload->senderHasAnyPermissions($sender)){
				$overloads[] = $overload;
			}
		}
		return $overloads;
	}

	/**
	 * TODO: maybe we should get rid of this, it rarely makes sense to send all possible usages of the command
	 * More likely we'll want to send the best matching usages based on the valid parameters we were able to match
	 *
	 * @return Translatable[]
	 * @phpstan-return list<Translatable>
	 */
	public function getUsages(CommandSender $sender, string $aliasUsed) : array{
		$usages = [];
		foreach($this->getPermittedOverloads($sender) as $overload){
			$usages[] = new Translatable("/$aliasUsed {%0}", [$overload->getUsage()]);
		}

		return $usages;
	}

	final public function getNamespace() : string{
		return $this->namespace;
	}

	/**
	 * Returns the local identifier of the command (without namespace or leading slash).
	 * This cannot be changed after creation.
	 */
	final public function getName() : string{
		return $this->name;
	}

	/**
	 * Returns the globally unique ID for the command. This typically looks like namespace:name
	 */
	final public function getId() : string{
		return "$this->namespace:$this->name";
	}

	/**
	 * @param string[] $permissions
	 * @phpstan-param list<string> $permissions
	 */
	protected function sendBadPermissionMessage(string $context, CommandSender $sender, array $permissions) : void{
		$message = $this->permissionMessage ?? KnownTranslationFactory::pocketmine_command_error_permission($context);
		if($message instanceof Translatable){
			$sender->sendMessage($message->prefix(TextFormat::RED));
		}elseif($message !== ""){
			$sender->sendMessage(str_replace("<permission>", implode(";", $permissions), $message));
		}
	}

	public function getPermissionMessage() : Translatable|string|null{
		return $this->permissionMessage;
	}

	public function getDescription() : Translatable|string{
		return $this->description;
	}

	public function setDescription(Translatable|string $description) : void{
		$this->description = $description;
	}

	public function setPermissionMessage(Translatable|string $permissionMessage) : void{
		$this->permissionMessage = $permissionMessage;
	}

	public static function broadcastCommandMessage(CommandSender $source, Translatable|string $message, bool $sendToSource = true) : void{
		$users = $source->getServer()->getBroadcastChannelSubscribers(Server::BROADCAST_CHANNEL_ADMINISTRATIVE);
		$result = KnownTranslationFactory::chat_type_admin($source->getName(), $message);
		$colored = $result->prefix(TextFormat::GRAY . TextFormat::ITALIC);

		if($sendToSource){
			$source->sendMessage($message);
		}

		foreach($users as $user){
			if($user instanceof BroadcastLoggerForwarder){
				$user->sendMessage($result);
			}elseif($user !== $source){
				$user->sendMessage($colored);
			}
		}
	}

	protected static function fetchPermittedPlayerTarget(
		CommandSender $sender,
		?string $target,
		string $selfPermission,
		string $otherPermission
	) : ?Player{
		//TODO: we need proper command selector support, but this one is useful and easy to hack in for now
		if($target !== null && $target !== "@s"){
			$player = $sender->getServer()->getPlayerByPrefix($target);
		}elseif($sender instanceof Player){
			$player = $sender;
		}else{
			throw new InvalidCommandSyntaxException();
		}

		if($player === null){
			$sender->sendMessage(KnownTranslationFactory::commands_generic_player_notFound()->prefix(TextFormat::RED));
			return null;
		}

		$permission = $player === $sender ? $selfPermission : $otherPermission;
		if(!$sender->hasPermission($permission)){
			$sender->sendMessage(TextFormat::RED . "You don't have permission to use this command on others");
			return null;
		}
		return $player;
	}
}
