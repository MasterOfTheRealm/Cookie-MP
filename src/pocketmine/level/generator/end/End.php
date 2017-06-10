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

namespace pocketmine\level\generator\end;

use pocketmine\block\Block;
use pocketmine\level\ChunkManager;
use pocketmine\level\generator\biome\Biome;
use pocketmine\level\generator\biome\BiomeSelector;
use pocketmine\level\generator\Generator;
use pocketmine\level\generator\noise\Simplex;
use pocketmine\level\generator\object\EndIsland;
use pocketmine\level\generator\populator\Populator;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;

class End extends Generator {

	private static $GAUSSIAN_KERNEL = null;
	private static $SMOOTH_SIZE = 2;
	/** @var Populator[] */
	private $populators = [];
	/** @var ChunkManager */
	private $level;
	/** @var Random */
	private $random;
	private $emptyHeight = 64;
	private $emptyAmplitude = 1;
	private $density = 0.5;
	private $bedrockDepth = 5;
	/** @var Populator[] */
	private $generationPopulators = [];
	/** @var Simplex */
	private $noiseBase;
	/** @var BiomeSelector */
	private $selector;
	/** @var EndIsland */
	private $smallIsland;

	public function __construct(array $options = []) {
		if (self::$GAUSSIAN_KERNEL === null) {
			self::generateKernel();
		}
		$this->smallIsland = new EndIsland();
	}

	private static function generateKernel() {
		self::$GAUSSIAN_KERNEL = [];

		$bellSize = 1 / self::$SMOOTH_SIZE;
		$bellHeight = 2 * self::$SMOOTH_SIZE;

		for ($sx = -self::$SMOOTH_SIZE; $sx <= self::$SMOOTH_SIZE; ++$sx) {
			self::$GAUSSIAN_KERNEL[$sx + self::$SMOOTH_SIZE] = [];

			for ($sz = -self::$SMOOTH_SIZE; $sz <= self::$SMOOTH_SIZE; ++$sz) {
				$bx = $bellSize * $sx;
				$bz = $bellSize * $sz;
				self::$GAUSSIAN_KERNEL[$sx + self::$SMOOTH_SIZE][$sz + self::$SMOOTH_SIZE] = $bellHeight * exp(-($bx * $bx + $bz * $bz) / 2);
			}
		}
	}

	public function getName() {
		return "end";
	}

	public function getSettings() {
		return [];
	}

	public function init(ChunkManager $level, Random $random) {
		$this->level = $level;
		$this->random = $random;
		$this->random->setSeed($this->level->getSeed());
		$this->noiseBase = new Simplex($this->random, 4, 1 / 4, 1 / 64);
	}

	public function generateChunk($chunkX, $chunkZ) {
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->level->getSeed());

		$noise = Generator::getFastNoise3D($this->noiseBase, 16, 128, 16, 4, 8, 4, $chunkX * 16, 0, $chunkZ * 16);

		$chunk = $this->level->getChunk($chunkX, $chunkZ);

		for ($x = 0; $x < 16; ++$x) {
			for ($z = 0; $z < 16; ++$z) {

				$biome = Biome::getBiome(Biome::END);
				$chunk->setBiomeId($x, $z, $biome->getId());

				for ($y = 0; $y < 128; ++$y) {
					$noiseValue = (abs($this->emptyHeight - $y) / $this->emptyHeight) * $this->emptyAmplitude - $noise[$x][$z][$y];
					$noiseValue -= 1 - $this->density;

					#if ($noiseValue > 0) {
					#	$chunk->setBlockId($x, $y, $z, Block::AIR);
					#} else {
					#	$chunk->setBlockId($x, $y, $z, Block::END_STONE);
					#}
				}
			}
		}

		foreach ($this->generationPopulators as $populator) {
			$populator->populate($this->level, $chunkX, $chunkZ, $this->random);
		}
	}

	public function populateChunk($chunkX, $chunkZ) {
		$start = new Vector3($chunkX * 16, 0, $chunkZ * 16);
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->level->getSeed());
		foreach ($this->populators as $populator) {
			$populator->populate($this->level, $chunkX, $chunkZ, $this->random);
		}

		if (($chunkX ** 2 + $chunkZ ** 2) > 4096) {
			if ($this->random->nextBoundedInt(14) == 0) { //Todo check for nearby islands
				$vec3 = $start->add(8 + $this->random->nextBoundedInt(16), 55 + $this->random->nextBoundedInt(16), 8 + $this->random->nextBoundedInt(16));
				$this->smallIsland->placeObject($this->level, $vec3->x, $vec3->y, $vec3->z, $this->random);
				var_dump($vec3);
			}
		}

		$chunk = $this->level->getChunk($chunkX, $chunkZ);
		$biome = Biome::getBiome($chunk->getBiomeId(7, 7));
		$biome->populateChunk($this->level, $chunkX, $chunkZ, $this->random);
	}

	public function getSpawn() {
		return new Vector3(127.5, 128, 127.5);
	}

}