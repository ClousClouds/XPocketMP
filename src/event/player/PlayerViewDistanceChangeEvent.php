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

namespace pocketmine\event\player;

use pocketmine\player\Player;

/**
 * Called when a player requests a different viewing distance than the current one.
 */
class PlayerViewDistanceChangeEvent extends PlayerEvent{
	protected int $newDistance;
	protected int $oldDistance;

	public function __construct(Player $player, int $oldDistance, int $newDistance){
		$this->player = $player;
		$this->oldDistance = $oldDistance;
		$this->newDistance = $newDistance;
	}

	/**
	 * Returns an int corresponding to the number of chunks forming the new view distance
	 */
	public function getNewDistance() : int{
		return $this->newDistance;
	}

	/**
	 * Returns an int corresponding to the number of chunks forming the previous view distance, before change
	 * If the player connects to the server, it will return -1
	 */
	public function getOldDistance() : int{
		return $this->oldDistance;
	}
}
