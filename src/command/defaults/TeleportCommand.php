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
use pocketmine\command\overload\BranchingOverload;
use pocketmine\command\overload\BranchingOverloadBuilder;
use pocketmine\command\overload\FloatRangeParameter;
use pocketmine\command\overload\RelativeFloat;
use pocketmine\command\overload\RelativeFloatParameter;
use pocketmine\command\overload\StringParameter;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\entity\Location;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\Limits;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;
use function round;

class TeleportCommand extends Command{

	public function __construct(string $namespace, string $name){
		parent::__construct(
			$namespace,
			$name,
			BranchingOverloadBuilder::make()
				->branch(
					[new StringParameter("teleportedPlayerName", "player to teleport")],
					fn(BranchingOverloadBuilder $childBuilder) : BranchingOverload => self::buildOverloads(
						$childBuilder,
						DefaultPermissionNames::COMMAND_TELEPORT_OTHER,
						self::tpOtherToPlayer(...),
						self::tpOtherCoords(...)
					)
				)
				->branch(
					[],
					fn(BranchingOverloadBuilder $childBuilder) : BranchingOverload => self::buildOverloads(
						$childBuilder,
						DefaultPermissionNames::COMMAND_TELEPORT_SELF,
						self::tpSelfToPlayer(...),
						self::tpSelfCoords(...)
					)
				)
				->build(),
			KnownTranslationFactory::pocketmine_command_tp_description()
		);
	}

	/**
	 * @phpstan-param anyClosure $tpToPlayer
	 * @phpstan-param anyClosure $tpToCoords
	 */
	private static function buildOverloads(BranchingOverloadBuilder $childBuilder, string $permission, \Closure $tpToPlayer, \Closure $tpToCoords) : BranchingOverload{
		return $childBuilder
			->executor([
				new StringParameter("destinationPlayerName", "destination player")
			], $permission, $tpToPlayer)
			->executor([
				new RelativeFloatParameter("xIn", "x"),
				new RelativeFloatParameter("yIn", "y"),
				new RelativeFloatParameter("zIn", "z"),
				new FloatRangeParameter("yaw", "yaw", 0, 360),
				new FloatRangeParameter("pitch", "pitch", -90, 90)
			], $permission, $tpToCoords)
			->build();
	}

	private static function tpCoords(
		CommandSender $sender,
		Player $subject,
		RelativeFloat $xIn,
		RelativeFloat $yIn,
		RelativeFloat $zIn,
		float $yaw,
		float $pitch
	) : void{
		$base = $subject->getLocation();

		$x = $xIn->resolve($base->x, Limits::INT32_MIN, Limits::INT32_MAX);
		$y = $yIn->resolve($base->y, World::Y_MIN, World::Y_MAX);
		$z = $zIn->resolve($base->z, Limits::INT32_MIN, Limits::INT32_MAX);
		$targetLocation = new Location($x, $y, $z, $base->getWorld(), $yaw, $pitch);

		$subject->teleport($targetLocation);
		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_tp_success_coordinates(
			$subject->getName(),
			(string) round($targetLocation->x, 2),
			(string) round($targetLocation->y, 2),
			(string) round($targetLocation->z, 2)
		));
	}

	private static function tpSelfCoords(
		CommandSender $sender,
		RelativeFloat $xIn,
		RelativeFloat $yIn,
		RelativeFloat $zIn,
		float $yaw = 0.0,
		float $pitch = 0.0
	) : void{
		if(!$sender instanceof Player){
			throw new InvalidCommandSyntaxException("This syntax can only be used as a player");
		}

		self::tpCoords($sender, $sender, $xIn, $yIn, $zIn, $yaw, $pitch);
	}

	private static function tpOtherCoords(
		CommandSender $sender,
		string $teleportedPlayerName,
		RelativeFloat $xIn,
		RelativeFloat $yIn,
		RelativeFloat $zIn,
		float $yaw = 0.0,
		float $pitch = 0.0
	) : void{
		$subject = self::fetchPermittedPlayerTarget($sender, $teleportedPlayerName, DefaultPermissionNames::COMMAND_TELEPORT_SELF, DefaultPermissionNames::COMMAND_TELEPORT_OTHER);
		if($subject === null){
			return;
		}

		self::tpCoords($sender, $subject, $xIn, $yIn, $zIn, $yaw, $pitch);
	}

	private static function tpToPlayer(CommandSender $sender, Player $teleportedPlayer, string $destinationPlayerName) : void{
		$destination = $sender->getServer()->getPlayerByPrefix($destinationPlayerName);
		if($destination === null){
			//TODO: this isn't really a syntax error, but we don't have strings for it currently
			$sender->sendMessage(TextFormat::RED . "Cannot find destination player: $destinationPlayerName");
			return;
		}

		$teleportedPlayer->teleport($destination->getLocation());
		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_tp_success($teleportedPlayer->getName(), $destination->getName()));
	}

	private static function tpSelfToPlayer(CommandSender $sender, string $destinationPlayer) : void{
		if(!$sender instanceof Player){
			throw new InvalidCommandSyntaxException("This syntax can only be used as a player");
		}

		self::tpToPlayer($sender, $sender, $destinationPlayer);
	}

	private static function tpOtherToPlayer(CommandSender $sender, string $teleportedPlayerName, string $destinationPlayerName) : void{
		$subject = self::fetchPermittedPlayerTarget($sender, $teleportedPlayerName, DefaultPermissionNames::COMMAND_TELEPORT_SELF, DefaultPermissionNames::COMMAND_TELEPORT_OTHER);
		if($subject === null){
			return;
		}

		self::tpToPlayer($sender, $subject, $destinationPlayerName);
	}
}
