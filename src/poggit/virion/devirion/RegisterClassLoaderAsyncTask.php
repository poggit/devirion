<?php

declare(strict_types=1);

/*
 * devirion
 *
 * Copyright (C) 2017-2018 Poggit
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

use pocketmine\scheduler\AsyncTask;

class RegisterClassLoaderAsyncTask extends AsyncTask{
	/** @var VirionClassLoader */
	private $classLoader;

	public function __construct(VirionClassLoader $classLoader){
		$this->classLoader = $classLoader;
	}

	public function onRun() : void{
		$this->classLoader->register(true);
	}
}
