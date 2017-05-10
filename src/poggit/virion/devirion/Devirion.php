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

class Devirion extends PluginBase{
	private $classLoader;

	public function onEnable(){
		$this->classLoader = new DevirionClassLoader($this->getServer()->getLoader());
		$dir = $this->getServer()->getDataPath() . "libs/";

		if(is_dir($dir)){
			$directory = dir($dir);
			while(is_string($file = $directory->read())){
				if(is_dir($dir . $file) and $file !== "." and $file !== ".."){
					$path = $dir . rtrim($file, "\\/") . "/";
				}elseif(is_file($dir . $file)){
					if(substr($file, -5) === ".phar"){
						$path = "phar://" . trim(str_replace(DIRECTORY_SEPARATOR, "/", realpath($dir . $file)), "/") . "/";
					}
				}
				if(!isset($path)){
					continue;
				}

				if(!is_file($path . "virion.yml")){
					continue;
				}
				$data = yaml_parse_file($path . "virion.yml");
				if(!is_array($data)){
					$this->getLogger()->error("Cannot load virion: Error parsing {$path}virion.yml");
					continue;
				}
				if(!isset($data["name"])){
					$this->getLogger()->error("Cannot load virion: Attribute 'name' missing in {$path}virion.yml");
					continue;
				}
				$name = $data["name"];
				$authors = [];
				if(isset($data["author"])){
					$authors[] = $data["author"];
				}
				if(isset($data["authors"])){
					$authors[] = array_merge($authors, $data["authors"]);
				}
				if(!isset($data["version"])){
					$this->getLogger()->error("Cannot load virion: Attribute 'version' missing in {$path}virion.yml");
					continue;
				}
				$version = $data["version"];
				if(!isset($data["antigen"])){
					$this->getLogger()->error("Cannot load virion: Attribute 'antigen' missing in {$path}virion.yml");
					continue;
				}
				$antigen = $data["antigen"];

				$this->getLogger()->info("Loading virion $name v$version by " . implode(", ", $authors));

				$this->classLoader->addAntigen($antigen, $path);
				$loaded = true;
			}

			if(isset($loaded)){
				$this->getLogger()->warning("Virions should be bundled into plugins, not redistributed separately! Only use devirion for debugging purposes!");
			}
			$directory->close();
		}
	}
}
