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

// Inizialize OCDropbox lib
$ocd = OCDropbox::getInstance();
// Start import
$ocd->importDropboxData($cli);

$cli->output( 'All done! Have a nice trip!' . "\n\n" );

?>