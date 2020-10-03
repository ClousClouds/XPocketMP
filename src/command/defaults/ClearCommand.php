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

use InvalidArgumentException;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\item\LegacyStringToItemParser;
use pocketmine\lang\TranslationContainer;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function array_merge;
use function count;
use function is_numeric;

class ClearCommand extends VanillaCommand{

	public function __construct(string $name){
		parent::__construct(
			$name,
			"%pocketmine.command.clear.description",
			"%pocketmine.command.clear.usage"
		);
		$this->setPermission("pocketmine.command.clear.self;pocketmine.command.clear.other");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(!$this->testPermission($sender)){
			return true;
		}

		if(count($args) > 3){
			throw new InvalidCommandSyntaxException();
		}

		$target = null;
		if($sender instanceof Player){
			if(!$sender->hasPermission("pocketmine.command.clear.self")){
				$sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.generic.permission"));
				return true;
			}

			$target = $sender;
		}else{
			if(count($args) < 1){
				throw new InvalidCommandSyntaxException();
			}
		}

		if(isset($args[0])){
			if(!$sender->hasPermission("pocketmine.command.clear.other")){
				$sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.generic.permission"));
				return true;
			}

			$target = $sender->getServer()->getPlayer($args[0]);
		}

		if($target === null){
			$sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.generic.player.notFound"));
			return true;
		}

		$item = null;
		if(isset($args[1])){
			try{
				$item = LegacyStringToItemParser::getInstance()->parse($args[1]);
			}catch(InvalidArgumentException $e){
				//vanilla checks this at argument parsing layer, can't come up with a better alternative
				$sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.give.item.notFound", [$args[1]]));
				return true;
			}
		}

		$maxCount = -1;
		if(isset($args[2])){
			if(!is_numeric($args[2])){
				$maxCount = -1;
			}

			if($item !== null){
				$item->setCount($maxCount = $this->getInteger($sender, $args[2], 0));
			}
		}

		//checking players inventory for all the items matching the criteria
		if($item !== null and $item->getMeta() === 0 and $maxCount === 0){
			$count = 0;
			$contents = array_merge($target->getInventory()->all($item), $target->getArmorInventory()->all($item));
			foreach($contents as $content){
				$count += $content->getCount();
			}

			if($count > 0){
				$sender->sendMessage(new TranslationContainer("%commands.clear.testing", [$target->getName(), $count]));
			}else{
				$sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.clear.failure.no.items", [$target->getName()]));
			}

			return true;
		}

		$cleared = 0;

		//clear everything from the targets inventory
		if($item === null){
			$contents = array_merge($target->getInventory()->getContents(), $target->getArmorInventory()->getContents());
			foreach($contents as $content){
				$cleared += $content->getCount();
			}

			$target->getInventory()->clearAll();
			$target->getArmorInventory()->clearAll();
			$target->getCursorInventory()->clearAll();
		}else{
			//clear the item from targets inventory irrelevant of the count
			if($maxCount === -1){
				foreach($target->getInventory()->all($item) as $index => $i){
					$cleared += $i->getCount();
					$target->getInventory()->clear($index);
				}
			}else{
				//clear only the given amount of that particular item from players inventory
				foreach($target->getInventory()->all($item) as $index => $i){
					if($i->getCount() >= $maxCount){
						$i->pop($maxCount);
						$cleared += $maxCount;
						$target->getInventory()->setItem($index, $i);
						break;
					}

					if($maxCount <= 0){
						break;
					}

					$cleared += $i->getCount();
					$maxCount -= $i->getCount();
					$target->getInventory()->clear($index);
				}
			}

			//vanilla behaviour, removes item from armor inventory, regardless of the count specified
			if(($index = $target->getArmorInventory()->first($item)) !== -1){
				$cleared++;
				$target->getArmorInventory()->clear($index);
			}
		}

		if($cleared > 0){
			$sender->sendMessage(new TranslationContainer("%commands.clear.success", [$target->getName(), $cleared]));
		}else{
			$sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.clear.failure.no.items", [$target->getName()]));
		}

		return true;
	}
}