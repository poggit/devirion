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

class VirionClassLoader extends \BaseClassLoader{
	private $antigenMap = [];
	private $mappedClasses = [];

	public function addAntigen(string $antigen, string $path){
		$this->antigenMap[$path] = $antigen;
	}

	public function findClass($class){
		$components = explode("\\", $class);
		$baseName = implode(DIRECTORY_SEPARATOR, $components);
		foreach($this->antigenMap as $path => $antigen){
			if(substr(strtolower($class), 0, strlen($antigen)) === strtolower($antigen)){

				if(PHP_INT_SIZE === 8 and file_exists($path . DIRECTORY_SEPARATOR . $baseName . "__64bit.php")){
					$this->mappedClasses[$class] = $antigen;
					return $path . DIRECTORY_SEPARATOR . $baseName . "__64bit.php";
				}elseif(PHP_INT_SIZE === 4 and file_exists($path . DIRECTORY_SEPARATOR . $baseName . "__32bit.php")){
					$this->mappedClasses[$class] = $antigen;
					return $path . DIRECTORY_SEPARATOR . $baseName . "__32bit.php";
				}elseif(file_exists($path . DIRECTORY_SEPARATOR . $baseName . ".php")){
					$this->mappedClasses[$class] = $antigen;
					return $path . DIRECTORY_SEPARATOR . $baseName . ".php";
				}
			}
		}
		return null;
	}

	public function getSourceViralAntigen(string $loadedClass){
		return $this->mappedClasses[$loadedClass] ?? null;
	}
}
