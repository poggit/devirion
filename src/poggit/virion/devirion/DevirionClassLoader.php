<?php

/*
 * devirion
 *
 * Copyright (C) 2016 Poggit
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace poggit\virion\devirion;

use pocketmine\CompatibleClassLoader;

class DevirionClassLoader extends CompatibleClassLoader{
	private $speciesMap = [];
	private $mappedClasses = [];

	public function addSpecies(string $species, string $path){
		$this->speciesMap[$path] = $species;
	}

	public function findClass($class){
		foreach($this->speciesMap as $path => $species){
			if(substr(strtolower($class), 0, strlen($species)) === strtolower($species)){
				$this->mappedClasses[$class] = $path;
			}
		}
	}

	public function findSpecies(string $loadedClass){
		return $this->mappedClasses[$loadedClass] ?? null;
	}
}
