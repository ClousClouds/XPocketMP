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

namespace pocketmine\command\parameter\defaults;

use pocketmine\command\CommandSender;
use pocketmine\command\parameter\Parameter;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use function is_numeric;

class FloatParameter extends Parameter{

	public function canParse(CommandSender $sender, string $argument) : bool{
		return is_numeric($argument);
	}

	public function parse(CommandSender $sender, string $argument){
		return (float) $argument;
	}

	public function getNetworkType() : int{
		return AvailableCommandsPacket::ARG_TYPE_FLOAT;
	}

	public function getTargetName() : string{
		return "float";
	}
}