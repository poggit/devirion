<?php

declare(strict_types=1);

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

use pocketmine\plugin\ApiVersion;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use function array_map;
use function array_merge;
use function array_pad;
use function count;
use function dir;
use function explode;
use function file_get_contents;
use function getopt;
use function implode;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function mkdir;
use function realpath;
use function rtrim;
use function str_replace;
use function substr;
use function yaml_parse;
use const PHP_MAJOR_VERSION;
use const PHP_MINOR_VERSION;

class DEVirion extends PluginBase{
	/** @var VirionClassLoader */
	private $classLoader;

	/**
	 * Called when the plugin is loaded, before calling onEnable()
	 */
	public function onLoad() : void{
		$this->classLoader = new VirionClassLoader();

		$dirs = [$this->getServer()->getDataPath() . "virions/"];
		foreach((array) (getopt("", ["load-virions::"])["load-virions"] ?? []) as $path){
			$dirs[] = $path;
		}
		foreach($dirs as $dir){
			if(!is_dir($dir)){
				@mkdir($dir);
			}
			$directory = dir($dir);
			while(is_string($file = $directory->read())){
				if(is_dir($dir . $file) and $file !== "." and $file !== ".."){
					$path = $dir . rtrim($file, "\\/") . "/";
				}elseif(is_file($dir . $file) && substr($file, -5) === ".phar"){
					$path = "phar://" . rtrim(str_replace(DIRECTORY_SEPARATOR, "/", realpath($dir . $file)), "/") . "/";
				}else{
					continue;
				}
				$this->loadVirion($path);
			}
			$directory->close();
		}

		foreach((array) (getopt("", ["load-virion::"])["load-virion"] ?? []) as $path){
			$this->loadVirion($path, true);
		}

		if(count($this->classLoader->getKnownAntigens()) > 0){
			$this->getLogger()->warning("Virions should be bundled into plugins, not redistributed separately! Do NOT use DEVirion on production servers!!");
			$this->classLoader->register(true);
			$size = $this->getServer()->getAsyncPool()->getSize();
			for($i = 0; $i < $size; $i++){
				$this->getServer()->getAsyncPool()->submitTaskToWorker(new RegisterClassLoaderAsyncTask($this->classLoader), $i);
			}
		}
	}

	/**
	 * Calling RepeatingTask after onLoad() to prevent issues with "Task when not enabled"
	 */
	public function onEnable() : void{
		if(count($this->classLoader->getKnownAntigens()) > 0){
			$this->getScheduler()->scheduleRepeatingTask(new class($this) extends Task{
				/** @var DEVirion */
				private $plugin;

				public function __construct(DEVirion $plugin){
					$this->plugin = $plugin;
				}

				public function onRun() : void{
					$messages = $this->plugin->getVirionClassLoader()->getMessages();
					while($messages->count() > 0){
						$this->plugin->getLogger()->warning($messages->shift());
					}
				}
			}, 1);
		}
	}

	public function loadVirion(string $path, bool $explicit = false) : void{
		if(!is_file($path . "virion.yml")){
			if($explicit){
				$this->getLogger()->error("Cannot load virion: virion.yml missing");
			}
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
				$parts = array_map("intval", array_pad(explode(".", (string) $php), 2, "0"));
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
		if(isset($data["api"]) && !ApiVersion::isCompatible($this->getServer()->getApiVersion(), (array) $data["api"])){
			$this->getLogger()->error("Cannot load virion $name: Server has incompatible API version {$this->getServer()->getApiVersion()}");
			return;

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
