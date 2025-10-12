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
use pocketmine\command\overload\CommandOverload;
use pocketmine\command\overload\RelativeFloat;
use pocketmine\command\overload\RelativeFloatParameter;
use pocketmine\command\overload\StringParameter;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\Limits;
use pocketmine\world\Position;
use pocketmine\world\World;
use function round;

class SpawnpointCommand extends Command{

	private const string SELF_PERM = DefaultPermissionNames::COMMAND_SPAWNPOINT_SELF;
	private const string OTHER_PERM = DefaultPermissionNames::COMMAND_SPAWNPOINT_OTHER;

	private const OVERLOAD_PERMS = [self::SELF_PERM, self::OTHER_PERM];

	public function __construct(string $namespace, string $name){
		parent::__construct(
			$namespace,
			$name,
			[
				new CommandOverload([
					new StringParameter("target", "target")
				], self::OVERLOAD_PERMS, self::setSpawnHere(...)),
				new CommandOverload([
					new StringParameter("target", "target"),
					new RelativeFloatParameter("x", "x"),
					new RelativeFloatParameter("y", "y"),
					new RelativeFloatParameter("z", "z")
				], self::OVERLOAD_PERMS, self::setSpawnCoords(...))
			],
			KnownTranslationFactory::pocketmine_command_spawnpoint_description()
		);
	}

	private static function setSpawnHere(CommandSender $sender, ?string $target = null) : void{
		$target = self::fetchPermittedPlayerTarget($sender, $target, self::SELF_PERM, self::OTHER_PERM);
		if($target === null){
			return;
		}

		$cpos = $target->getPosition();
		$pos = Position::fromObject($cpos->floor(), $cpos->getWorld());
		$target->setSpawn($pos);

		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_spawnpoint_success($target->getName(), (string) round($pos->x, 2), (string) round($pos->y, 2), (string) round($pos->z, 2)));
	}

	private static function setSpawnCoords(CommandSender $sender, string $target, RelativeFloat $x, RelativeFloat $y, RelativeFloat $z) : void{
		$target = self::fetchPermittedPlayerTarget($sender, $target, self::SELF_PERM, self::OTHER_PERM);
		if($target === null){
			return;
		}

		$world = $target->getWorld();
		$pos = $sender instanceof Player ? $sender->getPosition() : $world->getSpawnLocation();
		$x = $x->resolve($pos->x, Limits::INT32_MIN, Limits::INT32_MAX);
		$y = $y->resolve($pos->y, World::Y_MIN, World::Y_MAX);
		$z = $z->resolve($pos->z, Limits::INT32_MIN, Limits::INT32_MAX);
		$target->setSpawn(new Position($x, $y, $z, $world));

		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_spawnpoint_success($target->getName(), (string) round($x, 2), (string) round($y, 2), (string) round($z, 2)));

	}
}
