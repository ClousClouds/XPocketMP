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

namespace pocketmine\build\update_registry_annotations;

use pocketmine\utils\OverloadedRegistryMember;
use pocketmine\utils\Utils;
use function basename;
use function class_exists;
use function count;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function fwrite;
use function implode;
use function is_dir;
use function ksort;
use function lcfirst;
use function mb_strtoupper;
use function preg_match;
use function sprintf;
use function str_ends_with;
use function str_replace;
use const SORT_STRING;
use const STDERR;

if(count($argv) !== 2){
	fwrite(STDERR, "Provide a path to process\n");
	exit(1);
}

/**
 * @phpstan-param \ReflectionClass<*> $class
 */
function makeTypehint(string $namespaceName, \ReflectionClass $class) : string{
	return $class->getNamespaceName() === $namespaceName ? $class->getShortName() : '\\' . $class->getName();
}

/**
 * @param object[] $members
 * @phpstan-param array<string, object> $members
 * @phpstan-param array<string, OverloadedRegistryMember> $overloadedMembers
 */
function generateMethodAnnotations(string $namespaceName, array $members, array $overloadedMembers) : string{
	$selfName = basename(__FILE__);
	$lines = ["/**"];
	$lines[] = " * This doc-block is generated automatically, do not modify it manually.";
	$lines[] = " * This must be regenerated whenever registry members are added, removed or changed.";
	$lines[] = " * @see build/$selfName";
	$lines[] = " * @generate-registry-docblock";
	$lines[] = " *";

	static $lineTmpl = " * @method static %2\$s %s()";
	$memberLines = [];
	foreach(Utils::stringifyKeys($members) as $name => $member){
		$reflect = new \ReflectionClass($member);
		while($reflect !== false && $reflect->isAnonymous()){
			$reflect = $reflect->getParentClass();
		}
		if($reflect === false){
			$typehint = "object";
		}else{
			$typehint = makeTypehint($namespaceName, $reflect);
		}
		$accessor = mb_strtoupper($name);
		$memberLines[$accessor] = sprintf($lineTmpl, $accessor, $typehint);
	}
	foreach(Utils::stringifyKeys($overloadedMembers) as $baseName => $member){
		$accessor = mb_strtoupper($baseName);
		$returnTypehint = makeTypehint($namespaceName, new \ReflectionClass($member->memberClass));
		$enumReflect = new \ReflectionClass($member->enumClass);
		$paramTypehint = makeTypehint($namespaceName, $enumReflect);

		$memberLines[] = sprintf(" * @method static %s %s(%s \$%s)", $returnTypehint, $accessor, $paramTypehint, lcfirst($enumReflect->getShortName()));
	}
	ksort($memberLines, SORT_STRING);

	foreach($memberLines as $line){
		$lines[] = $line;
	}
	$lines[] = " */";
	return implode("\n", $lines);
}

function processFile(string $file) : void{
	$contents = file_get_contents($file);
	if($contents === false){
		throw new \RuntimeException("Failed to get contents of $file");
	}

	if(preg_match("/(*ANYCRLF)^namespace (.+);$/m", $contents, $matches) !== 1 || preg_match('/(*ANYCRLF)^((final|abstract)\s+)?class /m', $contents) !== 1){
		return;
	}
	$shortClassName = basename($file, ".php");
	$className = $matches[1] . "\\" . $shortClassName;
	if(!class_exists($className)){
		return;
	}
	$reflect = new \ReflectionClass($className);
	$docComment = $reflect->getDocComment();
	if($docComment === false || preg_match("/(*ANYCRLF)^\s*\*\s*@generate-registry-docblock$/m", $docComment) !== 1){
		return;
	}
	echo "Found registry in $file\n";

	$replacement = generateMethodAnnotations($matches[1], $className::getAll(), $className::getAllOverloaded());

	$newContents = str_replace($docComment, $replacement, $contents);
	if($newContents !== $contents){
		echo "Writing changed file $file\n";
		file_put_contents($file, $newContents);
	}else{
		echo "No changes made to file $file\n";
	}
}

require dirname(__DIR__) . '/vendor/autoload.php';

if(is_dir($argv[1])){
	/** @var string $file */
	foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($argv[1], \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_PATHNAME)) as $file){
		if(!str_ends_with($file, ".php")){
			continue;
		}

		processFile($file);
	}
}else{
	processFile($argv[1]);
}
