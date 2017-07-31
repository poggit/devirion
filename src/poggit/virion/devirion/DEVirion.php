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
use pocketmine\scheduler\PluginTask;

class DEVirion extends PluginBase{
	/** @var VirionClassLoader */
	private $classLoader;

	public function onEnable(){
		$dir = $this->getServer()->getDataPath() . "virions/";

		if(!is_dir($dir)){
			mkdir($dir);
		}
		$this->classLoader = new VirionClassLoader($this->getServer()->getLoader());
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

		if(count($this->classLoader->getKnownAntigens()) > 0){
			$this->getLogger()->warning("Virions should be bundled into plugins, not redistributed separately! Do NOT use DeVirion on production servers!!");
			$this->classLoader->register(true);
			$size = $this->getServer()->getScheduler()->getAsyncTaskPoolSize();
			for($i = 0; $i < $size; $i++){
				$this->getServer()->getScheduler()->scheduleAsyncTaskToWorker(new RegisterClassLoaderAsyncTask($this->classLoader), $i);
			}
			$this->getServer()->getScheduler()->scheduleRepeatingTask(new class($this) extends PluginTask{
				public function onRun(int $currentTick){
					/** @var DEVirion $owner */
					$owner = $this->getOwner();
					$messages = $owner->getVirionClassLoader()->getMessages();
					while($messages->count() > 0){
						$owner->getLogger()->warning($messages->shift());
					}
				}
			}, 1);
		}
	}

	public function loadVirion(string $path){
		if(!is_file($path . "virion.yml")){
//			$this->getLogger()->error("Cannot load virion: .poggit.yml missing");
			return;
		}
		$data = yaml_parse(file_get_contents($path . "virion.yml"));
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
			$this->getLogger()->error("Cannot load virion $name: Attribute 'version' missing in {$path}virion.yml");
			return;
		}
		$virionVersion = $data["version"];
		if(!isset($data["antigen"])){
			$this->getLogger()->error("Cannot load virion $name: Attribute 'antigen' missing in {$path}virion.yml");
			return;
		}
		if(isset($data["php"])){
			foreach((array) $data["php"] as $php){
				$parts = array_map("intval", array_pad(explode(".", $php), 2, "0"));
				if($parts[0] !== PHP_MAJOR_VERSION){
					continue;
				}
				if($parts[1] <= PHP_MINOR_VERSION){
					$ok = true;
					break;
				}
			}
			if(!isset($ok) and count((array) $data["php"]) > 0){
				$this->getLogger()->error("Cannot load virion $name: Server is using incompatible PHP version " . PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION);
				return;
			}
		}
		if(isset($data["api"])){
			$compatible = false;
			foreach((array) $data["api"] as $version){
				//Format: majorVersion.minorVersion.patch (3.0.0)
				//    or: majorVersion.minorVersion.patch-devBuild (3.0.0-alpha1)
				if($version !== $this->getServer()->getApiVersion()){
					$virionApi = array_pad(explode("-", $version), 2, ""); //0 = version, 1 = suffix (optional)
					$serverApi = array_pad(explode("-", $this->getServer()->getApiVersion()), 2, "");

					if(strtoupper($virionApi[1]) !== strtoupper($serverApi[1])){ //Different release phase (alpha vs. beta) or phase build (alpha.1 vs alpha.2)
						continue;
					}

					$virionNumbers = array_map("intval", explode(".", $virionApi[0]));
					$serverNumbers = array_map("intval", explode(".", $serverApi[0]));

					if($virionNumbers[0] !== $serverNumbers[0]){ //Completely different API version
						continue;
					}

					if($virionNumbers[1] > $serverNumbers[1]){ //If the plugin requires new API features, being backwards compatible
						continue;
					}
				}

				$compatible = true;
				break;
			}

			if($compatible === false){
				$this->getLogger()->error("Cannot load virion $name: Server has incompatible API version {$this->getServer()->getApiVersion()}");
				return;

			}
		}

		if(!isset($data["api"]) && !isset($data["php"])){
			$this->getLogger()->error("Cannot load virion $name: Either 'api' or 'php' attribute must be declared in {$path}virion.yml");
			return;
		}

		$antigen = $data["antigen"];

		$this->getLogger()->info("Loading virion $name v$virionVersion by " . implode(", ", $authors) . " (antigen: $antigen)");

		$this->classLoader->addAntigen($antigen, $path . "src/");
	}

	public function getVirionClassLoader() : VirionClassLoader{
		return $this->classLoader;
	}
}
