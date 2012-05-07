<?php
//error_reporting(E_ALL);
//ini_set( 'display_errors', 1 );
$module = $Params['Module'];

$ini  = eZINI::instance( 'dropbox.ini' );
$consumerKey    = $ini->variable( 'DropBoxConfig', 'ConsumerKey' );
$consumerSecret    = $ini->variable( 'DropBoxConfig', 'ConsumerSecret' );


if ( eZSiteData::fetchByName( 'dropbox_token' ) === NULL )
{
    $protocol = (!empty($_SERVER['HTTPS'])) ? 'https' : 'http';
    $callback = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    
    // Instantiate the required Dropbox objects
    $encrypter = new \Dropbox\OAuth\Storage\Encrypter( md5( 'ocdropbox_api' ) );
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
    else
    {
        print 'Error';
        eZExecution::cleanExit();
    }
}

$dropboxToken = eZSiteData::fetchByName( 'dropbox_token' );
$token = unserialize( $dropboxToken->attribute( 'value' ) );        

$encrypter = new \Dropbox\OAuth\Storage\Encrypter( md5( 'ocdropbox' ) );
$storage = new \Dropbox\OAuth\Storage\Session( $encrypter );
$storage->set( $token, 'access_token' );        

$OAuth = new \Dropbox\OAuth\Consumer\Curl( $consumerKey, $consumerSecret, $storage );
$dropbox = new \Dropbox\API( $OAuth );

$accountInfo = $dropbox->accountInfo();
eZDebug::writeNotice( var_export( $accountInfo, 1 ), 'OCDropbox' );

$ID = ( $Params['ID'] ) ? intval( $Params['ID'] ) : 0;
$tpl = eZTemplate::factory();

if ( $ID == 'disconnect' )
{
    $db = eZDB::instance();
    $db->begin();
    //$dropboxToken->remove();
    $db->commit();
    //$module->redirectToView( 'dashboard' );
}
else
{
    $ID = intval( $ID );
}

$tpl->setVariable( 'account', $accountInfo['body']->display_name );
//parent
$parent = false;
if ($ID > 0)
{    
    $parent = OCDropboxObject::fetch($ID);    
}
$tpl->setVariable( 'parent', $parent );

//lista
$list = OCDropboxObject::fetchByParentID( $ID );
$tpl->setVariable( 'list', $list );

//per il menu
$noParentList = OCDropboxObject::fetchByParentID( 0 );
$tpl->setVariable( 'noParentList', $noParentList );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:dropbox/dashboard.tpl' );
$Result['path'] = array( array( 'text' => 'Dropbox Dashboard' ,
                                'url' => 'dropbox/dashboard' ) );
if ($ID > 0)
{
    $Result['path'][] = array( 'text' => $parent->attribute('path') ,
                                'url' => false );
}
?>