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

namespace pocketmine\block;

use pocketmine\event\block\BlockGrowEvent;
use pocketmine\item\Fertilizer;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use function mt_rand;

class SweetBerryBush extends Crops {

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		switch ($blockReplace->getSide(Facing::DOWN)->getId()){

			//TODO: coarse dirt
			case BlockLegacyIds::GRASS:
			case BlockLegacyIds::DIRT:
			case BlockLegacyIds::PODZOL:
				return Block::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);

			default:
				return false;

		}
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if ($item instanceof Fertilizer){
			$block = clone $this;

			if (++$block->age >= 4){
				$block->age = 1; //set to young plant
				$this->pos->getWorld()->dropItem($this->pos, VanillaItems::SWEET_BERRIES()->setCount(mt_rand(2, 3)));
			}

			$ev = new BlockGrowEvent($this, $block);
			$ev->call();
			if(!$ev->isCancelled()) {
				$this->pos->getWorld()->setBlock($this->pos, $ev->getNewState());
			}

			$item->pop();

		} else{
			if($this->age >= 2){
				$this->pos->getWorld()->dropItem($this->pos, VanillaItems::SWEET_BERRIES()->setCount($this->age == 3 ? mt_rand(2, 3) : mt_rand(1, 2)));
			}
		}

		return true;
	}

	public function getDrops(Item $item): array{
		if ($this->age >= 2){
			return [
				VanillaItems::SWEET_BERRIES()->setCount($this->age == 3 ? mt_rand(2, 3) : mt_rand(1, 2))
			];
		}

		return [];
	}

	public function onNearbyBlockChange(): void{}

	public function getPickedItem(bool $addUserData = false): Item {
		return VanillaItems::SWEET_BERRIES();
	}
}
