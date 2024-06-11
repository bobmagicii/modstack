# Mod Stack Tool

Take a folder of "Mods" figure out which ones overwrite eachother, and then
link the final index to some destination directory. It will also save a list
of all the links created so it can remove them later.

Starfield no longer is loading ESMs and stuff from the `My Documents\Starfield`
and Mod Organiser 2 still does not support Gamepass. This takes all the mods,
generates a series of hardlinks to throw into the gamedir. It is not
specific to Starfield but thats what I built it for.



## Usage

### `php bin\mstack.php deploy`

Deploy all the mods to the destination.

### `php bin\mstack.php clean`

Clean the destination of all the links it made.

### {AppRoot}/mods

Each mod should go into a subfolder. I prefix the folders with numbers so that
when sorted the mod priority is pre-determined.

### {AppRoot}/data/deploy.index

List of all the links the application wrote to the destination directory.



## Todo

* A config file to define some application level choies. Like DestRoot.
