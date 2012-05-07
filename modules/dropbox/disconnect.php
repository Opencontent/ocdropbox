<?php

$module = $Params['Module'];
$dropboxToken = eZSiteData::fetchByName( 'dropbox_token' );
$db = eZDB::instance();
$db->begin();
$dropboxToken->remove();
$db->commit();
$module->redirectToView( 'dashboard' );

?>