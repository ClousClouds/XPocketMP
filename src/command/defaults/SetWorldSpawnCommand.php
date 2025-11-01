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

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\overload\BranchingOverloadBuilder;
use pocketmine\command\overload\RelativeFloat;
use pocketmine\command\overload\RelativeFloatParameter;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\math\Vector3;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\Limits;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;

class SetWorldSpawnCommand extends Command{

	public function __construct(string $namespace, string $name){
		parent::__construct(
			$namespace,
			$name,
			BranchingOverloadBuilder::make()
				->executor([], DefaultPermissionNames::COMMAND_SETWORLDSPAWN, self::setSpawnHere(...))
				->executor([
					new RelativeFloatParameter("x", "x"),
					new RelativeFloatParameter("y", "y"),
					new RelativeFloatParameter("z", "z")
				], DefaultPermissionNames::COMMAND_SETWORLDSPAWN, self::setSpawnCoordinates(...))
				->build(),
			KnownTranslationFactory::pocketmine_command_setworldspawn_description()
		);
	}

	private static function setSpawnHere(CommandSender $sender) : void{
		if(!$sender instanceof Player){
			$sender->sendMessage(TextFormat::RED . "You can only perform this command as a player");
			return;
		}
		$location = $sender->getPosition();
		$world = $location->getWorld();
		$pos = $location->asVector3()->floor();

		$world->setSpawnLocation($pos);

		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_setworldspawn_success((string) $pos->x, (string) $pos->y, (string) $pos->z));
	}

	private static function setSpawnCoordinates(CommandSender $sender, RelativeFloat $x, RelativeFloat $y, RelativeFloat $z) : void{
		if($sender instanceof Player){
			$base = $sender->getPosition();
			$world = $base->getWorld();
		}else{
			$base = new Vector3(0.0, 0.0, 0.0);
			$world = $sender->getServer()->getWorldManager()->getDefaultWorld();
		}
		$pos = (new Vector3(
			$x->resolve($base->x, Limits::INT32_MIN, Limits::INT32_MAX),
			$y->resolve($base->y, World::Y_MIN, World::Y_MAX),
			$z->resolve($base->z, Limits::INT32_MIN, Limits::INT32_MAX)
		))->floor();

		$world->setSpawnLocation($pos);

		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_setworldspawn_success((string) $pos->x, (string) $pos->y, (string) $pos->z));
	}
}
