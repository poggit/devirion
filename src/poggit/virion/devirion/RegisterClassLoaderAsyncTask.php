<?php

/*
 *
 * devirion
 *
 * Copyright (C) 2017 SOFe
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
*/

namespace poggit\virion\devirion;

use pocketmine\scheduler\AsyncTask;

class RegisterClassLoaderAsyncTask extends AsyncTask{
	/** @var VirionClassLoader */
	private $classLoader;

	public function __construct(VirionClassLoader $classLoader){
		$this->classLoader = $classLoader;
	}

	public function onRun(){
		$this->classLoader->register(true);
	}
}
