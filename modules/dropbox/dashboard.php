<?php

use \Dropbox as dbx;

//error_reporting(E_ALL);
//ini_set( 'display_errors', 1 );
$module = $Params['Module'];

$ini  = eZINI::instance( 'dropbox.ini' );
$consumerKey    = "57ri9pzz4ozwx5y"; //$ini->variable( 'DropBoxConfig', 'ConsumerKey' );
$consumerSecret    = "nrz5rdi7iurg329"; //$ini->variable( 'DropBoxConfig', 'ConsumerSecret' );
$accessType = $ini->hasVariable( 'DropBoxConfig', 'AccessType' ) ? $ini->variable( 'DropBoxConfig', 'AccessType' ) : "FullDropbox"; // or "AppFolder"

$jsonArray = array(
    "key" => $consumerKey,
    "secret" => $consumerSecret,
    "access_type" => $accessType
);
$json = json_encode( $jsonArray );
$appInfo = dbx\AppInfo::loadFromJson( $jsonArray );

$dbxConfig = new dbx\Config($appInfo, "PHP-eZPublish/1.0");
$webAuth = new dbx\WebAuth($dbxConfig);
list( $requestToken, $authorizeUrl ) = $webAuth->start( 'http://ez46:8888/backend/dropbox/dashboard' );

try
{
    list( $accessToken, $dropboxUserId ) = $webAuth->finish( $requestToken );
}
catch ( \Exception $e)
{
    header( 'Location: ' . $authorizeUrl );
    eZExecution::cleanExit();
}

print_r( $accessToken );

$dbxClient = new dbx\Client($dbxConfig, $accessToken);
$accountInfo = $dbxClient->getAccountInfo();

print_r($accountInfo);

?>