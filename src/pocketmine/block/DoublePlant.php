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

use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\Player;

class DoublePlant extends Flowable{
	private const BITFLAG_TOP = 0x08;

	protected $id = self::DOUBLE_PLANT;

	/** @var bool */
	protected $top = false;

	public function __construct(int $meta = 0){
		$this->setDamage($meta);
	}

	public function getDamage() : int{
		return $this->variant | ($this->top ? self::BITFLAG_TOP : 0);
	}

	public function setDamage(int $meta) : void{
		$this->variant = $meta & 0x07;
		$this->top = ($meta & self::BITFLAG_TOP) !== 0;
	}

	public function canBeReplaced() : bool{
		return $this->variant === 2 or $this->variant === 3; //grass or fern
	}

	public function getName() : string{
		static $names = [
			0 => "Sunflower",
			1 => "Lilac",
			2 => "Double Tallgrass",
			3 => "Large Fern",
			4 => "Rose Bush",
			5 => "Peony"
		];
		return $names[$this->getVariant()] ?? "";
	}

	public function place(Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, Player $player = null) : bool{
		$id = $blockReplace->getSide(Facing::DOWN)->getId();
		if(($id === Block::GRASS or $id === Block::DIRT) and $blockReplace->getSide(Facing::UP)->canBeReplaced()){
			$this->getLevel()->setBlock($blockReplace, $this, false, false);
			$top = clone $this;
			$top->top = true;
			$this->getLevel()->setBlock($blockReplace->getSide(Facing::UP), $top, false, false);

			return true;
		}

		return false;
	}

	/**
	 * Returns whether this double-plant has a corresponding other half.
	 * @return bool
	 */
	public function isValidHalfPlant() : bool{
		$other = $this->getSide($this->top ? Facing::DOWN : Facing::UP);

		return (
			$other instanceof DoublePlant and
			$other->getId() === $this->getId() and
			$other->getVariant() === $this->variant and
			$other->top !== $this->top
		);
	}

	public function onNearbyBlockChange() : void{
		if(!$this->isValidHalfPlant() or (!$this->top and $this->getSide(Facing::DOWN)->isTransparent())){
			$this->getLevel()->useBreakOn($this);
		}
	}

	public function getToolType() : int{
		return ($this->variant === 2 or $this->variant === 3) ? BlockToolType::TYPE_SHEARS : BlockToolType::TYPE_NONE;
	}

	public function getToolHarvestLevel() : int{
		return ($this->variant === 2 or $this->variant === 3) ? 1 : 0; //only grass or fern require shears
	}

	public function getDrops(Item $item) : array{
		if($this->top){
			if($this->isCompatibleWithTool($item)){
				return parent::getDrops($item);
			}

			if(mt_rand(0, 24) === 0){
				return [
					ItemFactory::get(Item::SEEDS)
				];
			}
		}

		return [];
	}

	public function getAffectedBlocks() : array{
		if($this->isValidHalfPlant()){
			return [$this, $this->getSide($this->top ? Facing::DOWN : Facing::UP)];
		}

		return parent::getAffectedBlocks();
	}
}
