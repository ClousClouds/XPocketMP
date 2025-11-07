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

namespace pocketmine\crafting;

use pocketmine\item\enchantment\AvailableEnchantmentRegistry;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;

/**
 * Recipe ingredient that matches enchanted books that can apply at least one enchantment to the target item.
 */
final class EnchantedBookRecipeIngredient implements RecipeIngredient{

	public function __construct(
		private Item $compareItem
	){}

	public function getCompareItem() : Item{ return $this->compareItem; }

	public function accepts(Item $item) : bool{
		if($item->getCount() < 1){
			return false;
		}

		if($item->getTypeId() !== ItemTypeIds::ENCHANTED_BOOK){
			// We only accept enchanted books in this ingredient
			return false;
		}

		$enchantmentRegistry = AvailableEnchantmentRegistry::getInstance();
		foreach($item->getEnchantments() as $compareEnchantment){
			if($enchantmentRegistry->isAvailableForItem($compareEnchantment->getType(), $this->compareItem)){
				// As long as one enchantment in the book is applicable to the target item
				// the combination is possible, so we accept this item
				return true;
			}
		}

		return false;

	}

	public function __toString() : string{
		return "EnchantedBookRecipeIngredient($this->compareItem)";
	}
}
