#!env php
<?php

////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////

$Hello = function(string $Bin, string $PF, string $PU): array {
	if($PF !== '')
	return [ dirname($PF), $PU, '/' ];

	return [ dirname($Bin, 2), dirname($Bin, 2), DIRECTORY_SEPARATOR ];
};

list($AppRoot, $BootRoot, $DS) = $Hello(
	__FILE__,
	Phar::Running(FALSE),
	Phar::Running(TRUE)
);

require(join($DS, [ $BootRoot, 'vendor', 'autoload.php' ]));

////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////

exit(Local\MStackApp::Realboot([
	'AppRoot'  => Nether\Common\Filesystem\Util::Repath($AppRoot),
	'BootRoot' => $BootRoot
]));
