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

namespace pocketmine\command\overload;

use DaveRandom\CallbackValidator\Type\NamedType;
use pocketmine\lang\Translatable;
use function preg_match;
use function strlen;

/**
 * @phpstan-extends Parameter<RelativeFloat>
 */
final class RelativeFloatParameter extends Parameter{

	public function __construct(string $codeName, Translatable|string $printableName){
		parent::__construct($codeName, $printableName, new NamedType(RelativeFloat::class));
	}

	public function parse(string $buffer, int &$offset) : RelativeFloat{
		if(preg_match('/\G(~)?(-?\d+\.?\d*)?/', $buffer, $matches, offset: $offset) > 0){
			$relativeRaw = $matches[1] ?? "";
			$valueRaw = $matches[2] ?? "";
			if($valueRaw !== "" || $relativeRaw !== ""){
				$offset += strlen($matches[0]);
				$relative = $relativeRaw === "~";
				$value = (float) $valueRaw;
				return new RelativeFloat($value, $relative);
			}
		}

		throw new ParameterParseException("Expected a float, possibly preceded by a ~ symbol");
	}
}
