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

use Generator;
use PHPUnit\Framework\TestCase;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use function floor;

class AnvilCraftTest extends TestCase{
	public static function materialRepairRecipe() : Generator{
		yield "No repair available" => [
			VanillaItems::DIAMOND_PICKAXE(),
			VanillaItems::DIAMOND(),
			null
		];

		yield "Repair one damage" => [
			VanillaItems::DIAMOND_PICKAXE()->setDamage(1),
			VanillaItems::DIAMOND(),
			new AnvilCraftResult(1, VanillaItems::DIAMOND_PICKAXE(), null)
		];

		yield "Repair one damage with more materials than expected" => [
			VanillaItems::DIAMOND_PICKAXE()->setDamage(1),
			VanillaItems::DIAMOND()->setCount(2),
			new AnvilCraftResult(1, VanillaItems::DIAMOND_PICKAXE(), VanillaItems::DIAMOND())
		];

		$diamondPickaxeQuarter = (int) floor(VanillaItems::DIAMOND_PICKAXE()->getMaxDurability() / 4);
		yield "Repair one quarter" => [
			VanillaItems::DIAMOND_PICKAXE()->setDamage($diamondPickaxeQuarter),
			VanillaItems::DIAMOND()->setCount(1),
			new AnvilCraftResult(1, VanillaItems::DIAMOND_PICKAXE(), null)
		];

		yield "Repair one quarter plus 1" => [
			VanillaItems::DIAMOND_PICKAXE()->setDamage($diamondPickaxeQuarter + 1),
			VanillaItems::DIAMOND()->setCount(1),
			new AnvilCraftResult(1, VanillaItems::DIAMOND_PICKAXE()->setDamage(1), null)
		];

		yield "Repair more than one quarter" => [
			VanillaItems::DIAMOND_PICKAXE()->setDamage($diamondPickaxeQuarter * 2),
			VanillaItems::DIAMOND()->setCount(2),
			new AnvilCraftResult(2, VanillaItems::DIAMOND_PICKAXE(), null)
		];

		yield "Repair more than one quarter with more materials than expected" => [
			VanillaItems::DIAMOND_PICKAXE()->setDamage($diamondPickaxeQuarter * 2),
			VanillaItems::DIAMOND()->setCount(3),
			new AnvilCraftResult(2, VanillaItems::DIAMOND_PICKAXE(), VanillaItems::DIAMOND()->setCount(1))
		];
	}

	/**
	 * @dataProvider materialRepairRecipe
	 */
	public function testMaterialRepairRecipe(Item $base, Item $material, ?AnvilCraftResult $expected) : void{
		$recipe = new MaterialRepairRecipe(
			new ExactRecipeIngredient((clone $base)->setCount(1)),
			new ExactRecipeIngredient((clone $material)->setCount(1))
		);
		$result = $recipe->getResultFor($base, $material);
		if($expected === null){
			self::assertNull($result, "Recipe did not match expected result");
			return;
		}else{
			self::assertNotNull($result, "Recipe did not match expected result");
		}
		self::assertEquals($expected->getXpCost(), $result->getXpCost(), "XP cost did not match expected result");
		self::assertTrue($expected->getOutput()->equalsExact($result->getOutput()), "Recipe output did not match expected result");
		$sacrificeResult = $expected->getSacrificeResult();
		if($sacrificeResult !== null){
			$resultExpected = $result->getSacrificeResult();
			self::assertNotNull($resultExpected, "Recipe sacrifice result did not match expected result");
			self::assertTrue($sacrificeResult->equalsExact($resultExpected), "Recipe sacrifice result did not match expected result");
		}else{
			self::assertNull($result->getSacrificeResult(), "Recipe sacrifice result did not match expected result");
		}
	}
}
