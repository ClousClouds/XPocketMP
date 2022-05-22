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

namespace pocketmine\block\tile;

use pocketmine\block\inventory\CampfireInventory;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\world\World;

class Campfire extends Spawnable implements Container{
	use ContainerTrait;

	public const TAG_COOKING_TIMES = "CookingTimes";

	protected CampfireInventory $inventory;
	/** @phpstan-var array<int, int> */
	private array $cookingTimes = [];

	public function __construct(World $world, Vector3 $pos){
		parent::__construct($world, $pos);
		$this->inventory = new CampfireInventory($this->position);
	}

	/**
	 * @return CampfireInventory
	 */
	public function getInventory(){
		return $this->inventory;
	}

	/**
	 * @return CampfireInventory
	 */
	public function getRealInventory(){
		return $this->inventory;
	}

	/**
	 * @phpstan-return array<int, int>
	 */
	public function getCookingTimes() : array{
		return $this->cookingTimes;
	}

	/**
	 * @phpstan-param array<int, int>
	 */
	public function setCookingTimes(array $cookingTime) : void{
		$this->cookingTimes = $cookingTime;
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->loadItems($nbt);

		if(($tag = $nbt->getTag(self::TAG_COOKING_TIMES)) !== null){
			/** @var IntTag $time */
			foreach($tag->getValue() as $slot => $time){
				$this->cookingTimes[$slot] = $time->getValue();
			}
		}
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$this->saveItems($nbt);

		$times = [];
		foreach($this->cookingTimes as $time){
			$times[] = new IntTag($time);
		}
		$nbt->setTag(self::TAG_COOKING_TIMES, new ListTag($times));
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		foreach($this->getInventory()->getContents() as $slot => $item){
			$nbt->setTag("Item" . $slot + 1, $item->nbtSerialize());
		}
	}
}