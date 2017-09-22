# DEVirion
DEVirion is the virion dependency manager. It can be used to run plugins from source if they require virions.

1. Install this plugin
2. Create a directory called `virions` **next to the `plugins` folder** (not inside it!)
3. Drop the virions you use into the `virions` folder. Both packaged (.phar) virions and source (folder) virions are acceptable. (Just like how you install plugins with DevTools)

### Notes
* DEVirion will **not** shade plugins. Hence, this plugin **must only** be used for virion/plugin development. **Released plugins must not depend on DEVirion to load virions**.
* DEVirion cannot automatically detect what virions are needed. Use the [CLI](#command-line-interface-standalone-executable) to automatically download virions required for a certain project.

## Command Line Options (for PocketMine)
You may also use the command-line option `--load-virions` to specify an _additional_ folder to scan virions from (similar to the plugin path), or `--load-virion` (singular) to explicitly load a **folder virion** at the specified path (does not work with phar virions). For example, if you have this folder structure:

```
/
/server/
/server/PocketMine-MP.phar
/server/start.sh
/server/virions/
/server/virions/libweird.phar
/server/virions/libstrange/ (with a virion.yml in this folder)
/libs/
/libs/libodd.phar
/libs/librare/ (with a virion.yml in this folder)
/misc/
/misc/libnormal.phar
/misc/libtrivial/ (with a virion.yml in this folder)
```

Running `./start.sh` in `/server/` will _automatically_ load libweird and libstrange.

Running `./start.sh --load-virions=/libs/` will _additionally_ load libodd and librare

Running `./start.sh --load-virion=/misc/libtrivial/` will _additionally_ load libtrivial.

Running `./start.sh --load-virion=/misc/libnormal.phar` will **not** load libnormal, because `--load-virion` does not support phar virions &mdash; it's usually not necessary, and you should copy it to `virions` folder instead.

## Command Line Interface (Standalone executable)
### Usage
```
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
```

### Requirements
To run the CLI,
* PHP 7.0 or above is required
* The OpenSSL extension must be present to provide the `https://` wrapper (cURL is not required)
* The YAML extension is required.
* (Will be fixed soon) The production phar may not contain the shebang line `#!/usr/bin/env php` in the stub, so directly executing `devirion.phar blah blah blah` may not work.
