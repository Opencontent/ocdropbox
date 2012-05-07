<?php

$ini  = eZINI::instance( 'dropbox.ini' );
$consumerKey    = $ini->variable( 'DropBoxConfig', 'ConsumerKey' );
$consumerSecret    = $ini->variable( 'DropBoxConfig', 'ConsumerSecret' );

$protocol = (!empty($_SERVER['HTTPS'])) ? 'https' : 'http';
$callback = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

// Instantiate the required Dropbox objects
$encrypter = new \Dropbox\OAuth\Storage\Encrypter( 'ocdropboxocdropboxocdropboxocdro' );
$storage = new \Dropbox\OAuth\Storage\Session( $encrypter );
$OAuth = new \Dropbox\OAuth\Consumer\Curl( $consumerKey, $consumerSecret, $storage, $callback );
$dropbox = new \Dropbox\API( $OAuth );

if ( ( $storage->get('access_token') ) )
{
    $sitedata = new eZSiteData( array(
        'name' => 'dropbox_token',
        'value' => serialize( $storage->get('access_token') )
    ));
    $sitedata->store();
}
?>