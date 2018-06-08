<?php

declare(strict_types=1);

/*
 * devirion
 *
 * Copyright (C) 2016-2018 Poggit
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

use BaseClassLoader;
use ClassLoader;
use Threaded;
use function file_exists;
use function str_replace;
use function stripos;
use const DIRECTORY_SEPARATOR;
use const PHP_INT_SIZE;

class VirionClassLoader extends BaseClassLoader{
	private $messages;

	/** @var Threaded|string[] */
	private $antigenMap;
	/** @var Threaded|string[] */
	private $mappedClasses;

	public function __construct(ClassLoader $parent = null){
		parent::__construct($parent);
		$this->messages = new Threaded;
		$this->antigenMap = new Threaded;
		$this->mappedClasses = new Threaded;
	}

	public function addAntigen(string $antigen, string $path) : void{
		$this->antigenMap[$path] = $antigen;
	}

	public function getKnownAntigens() : array{
		$antigens = [];
		foreach($this->antigenMap as $antigen){
			$antigens[] = $antigen;
		}
		return $antigens;
	}

	public function findClass($class) : ?string{
		$baseName = str_replace("\\", DIRECTORY_SEPARATOR, $class);
		foreach($this->antigenMap as $path => $antigen){
			if(stripos($class, $antigen) === 0){
				if(PHP_INT_SIZE === 8 and file_exists($path . DIRECTORY_SEPARATOR . $baseName . "__64bit.php")){
					$this->mappedClasses[$class] = $antigen;
					return $path . DIRECTORY_SEPARATOR . $baseName . "__64bit.php";
				}

				if(PHP_INT_SIZE === 4 and file_exists($path . DIRECTORY_SEPARATOR . $baseName . "__32bit.php")){
					$this->mappedClasses[$class] = $antigen;
					return $path . DIRECTORY_SEPARATOR . $baseName . "__32bit.php";
				}

				if(file_exists($path . DIRECTORY_SEPARATOR . $baseName . ".php")){
					$this->mappedClasses[$class] = $antigen;
					return $path . DIRECTORY_SEPARATOR . $baseName . ".php";
				}

				$this->messages[] = "DEVirion detected an attempt to load class $class, matching a known antigen but does not exist. Please note that this reference might be shaded in virion building and may fail to load.\n";
			}
		}

		return null;
	}

	public function loadClass($name) : ?bool{
		try{
			return parent::loadClass($name);
		}catch(\ClassNotFoundException $e){
			return null;
		}
	}

	public function getSourceViralAntigen(string $loadedClass) : ?string{
		return $this->mappedClasses[$loadedClass] ?? null;
	}

	public function getMessages() : Threaded{
		return $this->messages;
	}
}
