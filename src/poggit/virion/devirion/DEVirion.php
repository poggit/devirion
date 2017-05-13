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

use pocketmine\plugin\PluginBase;

class DEVirion extends PluginBase{
	/** @var VirionClassLoader */
	private $classLoader;

	public function onEnable(){
		$this->classLoader = new VirionClassLoader($this->getServer()->getLoader());
		$dir = $this->getServer()->getDataPath() . "virions/";

		if(is_dir($dir)){
			$directory = dir($dir);
			while(is_string($file = $directory->read())){
				if(is_dir($dir . $file) and $file !== "." and $file !== ".."){
					$path = $dir . rtrim($file, "\\/") . "/";
				}elseif(is_file($dir . $file) && substr($file, -5) === ".phar"){
					$path = "phar://" . trim(str_replace(DIRECTORY_SEPARATOR, "/", realpath($dir . $file)), "/") . "/";
				}else{
					continue;
				}
				$this->loadVirion($path);
			}

			$directory->close();
		}

		if(count($this->classLoader->getKnownAntigens()) > 0){
			$this->getLogger()->warning("Virions should be bundled into plugins, not redistributed separately! Do NOT use DeVirion on production servers!!");
		}
	}

	public function loadVirion(string $path){
		if(!is_file($path . "virion.yml")){
//			$this->getLogger()->error("Cannot load virion: .poggit.yml missing");
			return;
		}
		$data = yaml_parse_file($path . "virion.yml");
		if(!is_array($data)){
			$this->getLogger()->error("Cannot load virion: Error parsing {$path}virion.yml");
			return;
		}
		if(!isset($data["name"])){
			$this->getLogger()->error("Cannot load virion: Attribute 'name' missing in {$path}virion.yml");
			return;
		}
		$name = $data["name"];
		$authors = [];
		if(isset($data["author"])){
			$authors[] = $data["author"];
		}
		if(isset($data["authors"])){
			$authors = array_merge($authors, (array) $data["authors"]);
		}
		if(!isset($data["version"])){
			$this->getLogger()->error("Cannot load virion: Attribute 'version' missing in {$path}virion.yml");
			return;
		}
		$version = $data["version"];
		if(!isset($data["antigen"])){
			$this->getLogger()->error("Cannot load virion: Attribute 'antigen' missing in {$path}virion.yml");
			return;
		}
		$antigen = $data["antigen"];

		$this->getLogger()->info("Loading virion $name v$version by " . implode(", ", $authors) . " (antigen: $antigen)");

		$this->classLoader->addAntigen($antigen, $path);
	}
}
