<?php

$Module = array( 'name' => 'Dropbox',
                 'variable_params' => true );

$ViewList = array();

$ViewList['dashboard'] = array(
    'functions' => array( 'dashboard' ),
    'script' => 'dashboard.php',
    'default_navigation_part' => 'ocdropboxnavigationpart',
    'params' => array( 'ID' ),
    'unordered_params' => array( ) );


$FunctionList = array();
$FunctionList['dashboard'] = array();

?>
