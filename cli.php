<?php

const USAGE = /** @lang text */
<<<EOU
=== DEVirion.phar usage ===
php /path/to/DEVirion.phar install <manifest> <project> <folder> [--replace]
    - Downloads all virions required by a project
    - <manifest> is the path to the .poggit.yml of the project. You may put an
      URLs here.
    - <project> is the case-insensitive name of the project.
    - <folder> is the path to the folder that virions should be installed in.
      This should be /path/to/your_server/virions
    - --replace will cause DEVirion to overwrite existing virions with the same
      name DEVirion uses ({VirionName}_v{MajorVersion}.phar)

php /path/to/DEVirion.phar download <owner> <repo> <project> <version> [--branch=":default"] [--output="/path/to/output.phar"]
    - Downloads a virion build from Poggit
    - <owner>, <repo> and <project> are the case-insensitive names of the repo
      owner, repo and project containing the virion. <project> can be ~ if same
      as <repo>.
    - <version> is the SemVer version constraint for the virion required.
      Same as the version field in .poggit.yml
    - Only builds from the --branch branch will be used. ":default" means the
      current default branch of the repo. --branch or --branch=* will accept
      all branches.
    - If --output is provided, the virion will be downloaded to there.
      Otherwise, it is downloaded to <project>.phar (or "<project>_(n).phar" if
      exists). --output without a value will echo the file contents to stderr.
      (There is no option to echo to stdout because stdout is used for showing
      verbose information)

EOU;


if(!isset($argv[4])){
	echo USAGE;
	exit;
}


$opts = [];
$args = []; // I don't like overwriting $argv
foreach($argv as $i => $v){
	if(strpos($v, "--") === 0){
		$opts[] = $v;
	}else{
		$args[] = $v;
	}
}

if(!in_array("https", stream_get_wrappers(), true)){
	echo "[!] The openssl extension is required to run this tool\n";
	exit(2);
}

switch(strtolower($args[1])){
	case "install":
		if(!function_exists("yaml_parse")){
			echo "[!] YAML extension is required to use the install command\n";
			exit(2);
		}
		$manifest = $args[2];
		echo "[*] Loading $manifest...\n";
		$data = yaml_parse(file_get_contents($manifest));
		if(!is_array($data) || !isset($data["projects"])){
			echo "[!] Invalid .poggit.yml from $manifest\n";
			exit(1);
		}
		$projects = array_change_key_case($data["projects"], CASE_LOWER);
		if(!isset($projects[$projectName = strtolower($args[3])])){
			echo "[!] Project $projectName not found: $manifest\n";
			exit(1);
		}
		$project = $projects[$projectName];
		if(!isset($project["libs"]) || !is_array($project["libs"])){
			echo "[!] Project $projectName does not use any virions!\n";
			exit(1);
		}
		$installFolder = $args[4];
		if(!is_dir($installFolder)){
			echo "[*] Creating virion folder $installFolder\n";
			mkdir($installFolder);
		}
		$installFolder = rtrim($installFolder, "\\/") . "/";
		foreach($project["libs"] as $n => $lib){
			if(isset($lib["format"]) && $lib["format"] !== "virion"){
				echo "[!] Warning: Not processing library #$n because it is not in virion format:\n  ", str_replace("\n", "\n  ", yaml_emit($lib)) . "\n";
				continue;
			}
			if(!isset($lib["src"])){
				echo "[!] Library #$n does not contain src: attribute";
			}

			$src = $lib["src"];
			$vendor = strtolower($libDeclaration["vendor"] ?? "poggit-project");
			if($vendor === "raw"){
				if(strpos($src, "http://") === 0 || strpos($src, "https://") === 0){
					$file = $src;
				}else{
					$file = $manifest . "/../";
					if($src{0} === "/"){
						if(strlen($projectPath = trim($project["path"], "/")) > 0){
							$file .= $projectPath . "/";
						}
						$src = substr($src, 1);
					}
					$file .= $src;
				}
			}else{
				if($vendor !== "poggit-project"){
					echo "[!] For library #$n, unknown vendor $vendor, assumed 'poggit-project'\n";
				}

				if(!isset($lib["src"]) or
					count($srcParts = explode("/", trim($lib["src"], " \t\n\r\0\x0B/"))) === 0
				){
					echo "[!] For library #$n, 'src' attribute is missing, skipping\n";
					continue;
				}
				$srcProject = array_pop($srcParts);
				$srcRepo = array_pop($srcParts) ?? $project->repo[1];
				$srcOwner = array_pop($srcParts) ?? $project->repo[0];

				$version = $lib["version"] ?? "*";
				$branch = $libDeclaration["branch"] ?? ":default";

				$file = "https://poggit.pmmp.io/v.dl/$srcOwner/$srcRepo/" . urlencode($srcProject) . "/" . urlencode($version) . "?branch=" . urlencode($branch);
			}

			$url = fopen($file, "rb");
			$tmpStream = mkstemp("devirion_tmp_XXXXXX.phar", $tmpFile);
			echo "[*] Downloading virion from $file\n";
			stream_copy_to_stream($url, $tmpStream);
			fclose($tmpStream);
			fclose($url);
			try{
				$phar = new Phar($tmpFile);
				$virionYml = yaml_parse(file_get_contents($phar["virion.yml"]));
				if(!is_array($virionYml) || !isset($virionYml["name"], $virionYml["version"])){
					echo "[!] For library #$n, the phar file at $file is not a valid virion, skipping\n";
					continue;
				}
				$targetFile = $installFolder . $virionYml["name"] . "_v" . strstr($virionYml["version"], ".", true) . ".phar";
				if(!in_array("--replace", $opts, true) && is_file($targetFile)){
					echo "[!] Not replacing existing phar file $targetFile (use --replace if you want to)\n";
				}else{
					copy($tmpFile, $targetFile);
					echo "[*] Copied into $targetFile\n";
				}
			}catch(PharException $ex){
				echo "[!] A corrupted phar file was downloaded for library #$n ({$ex->getMessage()}), skipping\n";
			}catch(UnexpectedValueException $ex){
				echo "[!] A corrupted phar file was downloaded for library #$n ({$ex->getMessage()}), skipping\n";
			}
			unlink($tmpFile);
		}
		exit(0);

	case "download":
		if(!isset($args[5])){
			echo USAGE;
			exit;
		}

		list(, , $owner, $repo, $project, $version) = $args;
		$branch = ":default";
		$output = $project . ".phar";
		for($n = 2; is_file($output); ++$n){
			$output = $project . "_($n).phar";
		}
		foreach($opts as $opt){
			$parts = explode("=", substr($opt, 2));
			if($parts[0] === "branch"){
				$branch = $parts[1] ?? "*";
			}elseif($parts[0] === "output"){
				$output = $parts[1] ?? "php://stdout";
			}
		}
		$url = "https://poggit.pmmp.io/v.dl/$owner/$repo/" . urlencode($project) . "/" . urlencode($version) . "?branch=" . urlencode($branch);
		echo "[*] Downloading from $url\n";
		$url = fopen($url, "rb");
		$fh = fopen($output, "wb");
		stream_copy_to_stream($url, $fh);
		fclose($url);
		fclose($fh);
		echo "[*] Copied into $output\n";
		exit(0);
}

/**
 * @param $template
 * @param &$randomFile
 *
 * @return bool|resource
 *
 * @author mobius https://stackoverflow.com/a/8971248/3990767
 */
function mkstemp($template, &$randomFile){
	$attempts = 238328; // 62 x 62 x 62
	$letters = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
	$length = strlen($letters) - 1;

	if(strlen($template) < 6 || !strstr($template, 'XXXXXX')){
		return false;
	}

	for($count = 0; $count < $attempts; ++$count){
		$random = "";

		for($p = 0; $p < 6; $p++){
			$random .= $letters[mt_rand(0, $length)];
		}

		$randomFile = str_replace("XXXXXX", $random, $template);

		if(!($fd = @fopen($randomFile, "x+b"))){
			continue;
		}

		return $fd;
	}

	return false;
}
