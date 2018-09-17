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
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\Server;

class Sugarcane extends Flowable{

	protected $id = self::SUGARCANE_BLOCK;

	protected $itemId = Item::SUGARCANE;

	/** @var int */
	protected $age = 0;

	public function __construct(){

	}

	public function getDamage() : int{
		return $this->age;
	}

	public function setDamage(int $meta) : void{
		$this->age = $meta;
	}

	public function getName() : string{
		return "Sugarcane";
	}

	public function onActivate(Item $item, Player $player = null) : bool{
		if($item->getId() === Item::DYE and $item->getDamage() === 0x0F){ //Bonemeal
			if($this->getSide(Facing::DOWN)->getId() !== self::SUGARCANE_BLOCK){
				for($y = 1; $y < 3; ++$y){
					$b = $this->getLevel()->getBlockAt($this->x, $this->y + $y, $this->z);
					if($b->getId() === self::AIR){
						Server::getInstance()->getPluginManager()->callEvent($ev = new BlockGrowEvent($b, BlockFactory::get(Block::SUGARCANE_BLOCK)));
						if(!$ev->isCancelled()){
							$this->getLevel()->setBlock($b, $ev->getNewState(), true);
						}
						break;
					}
				}
				$this->age = 0;
				$this->getLevel()->setBlock($this, $this, true);
			}

			$item->count--;

			return true;
		}

		return false;
	}

	public function onNearbyBlockChange() : void{
		$down = $this->getSide(Facing::DOWN);
		if($down->isTransparent() and $down->getId() !== self::SUGARCANE_BLOCK){
			$this->getLevel()->useBreakOn($this);
		}
	}

	public function ticksRandomly() : bool{
		return true;
	}

	public function onRandomTick() : void{
		if($this->getSide(Facing::DOWN)->getId() !== self::SUGARCANE_BLOCK){
			if($this->age === 15){
				for($y = 1; $y < 3; ++$y){
					$b = $this->getLevel()->getBlockAt($this->x, $this->y + $y, $this->z);
					if($b->getId() === self::AIR){
						$this->getLevel()->setBlock($b, BlockFactory::get(Block::SUGARCANE_BLOCK), true);
						break;
					}
				}
				$this->age = 0;
				$this->getLevel()->setBlock($this, $this, true);
			}else{
				++$this->age;
				$this->getLevel()->setBlock($this, $this, true);
			}
		}
	}

	public function place(Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, Player $player = null) : bool{
		$down = $this->getSide(Facing::DOWN);
		if($down->getId() === self::SUGARCANE_BLOCK){
			return parent::place($item, $blockReplace, $blockClicked, $face, $clickVector, $player);
		}elseif($down->getId() === self::GRASS or $down->getId() === self::DIRT or $down->getId() === self::SAND){
			foreach(Facing::HORIZONTAL as $side){
				if($down->getSide($side) instanceof Water){
					return parent::place($item, $blockReplace, $blockClicked, $face, $clickVector, $player);
				}
			}
		}

		return false;
	}
}
