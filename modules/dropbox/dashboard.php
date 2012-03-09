<?php
$ID = ( $Params['ID'] ) ? $Params['ID'] : 0;
$tpl = eZTemplate::factory();

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