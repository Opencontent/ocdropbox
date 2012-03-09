<?php
set_time_limit ( 0 );
#require 'autoload.php';

try
{
    $cli = eZCLI::instance();
}
catch (Exception $e)
{
	print_r($e,true);
}

$script = eZScript::instance( array( 'description' => ( "eZ Publish data import.\n\n" . "Simple import script for use with eZ Publish"),
                                     'use-session' => false,
                                     'use-modules' => true,
                                     'use-extensions' => true,
                                     'debug-output' => false,
                                     'debug-message' =>true) );
 
$script->startup();
$script->initialize();
 
// Inizialize OCDropbox lib
$ocd = OCDropbox::getInstance();
// Start import
$ocd->importDropboxData($cli);

$cli->output( 'All done! Have a nice trip!' . "\n\n" );
$script->shutdown();
?>