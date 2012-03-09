<?php
/**
 * OCDropbox class 
 * 
 */

require_once('autoload.php');

class OCDropbox
{
    private static $instance;
    private $Data = array();
    private	$oauth;
	private	$dropbox;
	private $basePath;
    private $ini;
    
    public $rootNode;
    public $varDir;
    public $mapDropboxFolder;
    public $mapDropboxSubFolder;
    
    /**
     * Constructor
     * 
     */
    function __construct()
    {
        $this->ini  = eZINI::instance( 'dropbox.ini' );
        $consKey    = $this->ini->variable( 'DropBoxConfig', 'ConsumerKey' );
    	$consSec    = $this->ini->variable( 'DropBoxConfig', 'ConsumerSecret' );
    	$login      = $this->ini->variable( 'DropBoxConfig', 'DropBoxLogin' );
    	$password   = $this->ini->variable( 'DropBoxConfig', 'DropBoxPassword' );
        
        $siteIni = eZINI::instance( 'site.ini' );
        $var = $siteIni->variable( 'FileSettings','VarDir' );
        $this->varDir = $var . '/storage/original/dropbox/';
        if ( !is_dir( $this->varDir ) )
        {
            eZDir::mkdir( $this->varDir, false, true );
        }
        
        $this->mapDropboxFolder = $this->ini->variable( 'DropBoxConfig', 'MapDropboxFolder' );
        $this->mapDropboxSubFolder = $this->ini->variable( 'DropBoxConfig', 'MapDropboxSubFolder' );
        
        $this->basePath   = $this->ini->variable( 'DropBoxConfig', 'DropBoxBasePath' );
        
        if ( $this->ini->hasVariable( 'DropBoxConfig', 'ImportDropBoxRootNode' ) )
        {
            $this->rootNode = $this->ini->variable( 'DropBoxConfig', 'ImportDropBoxRootNode' );
        }
        else
        {
            $this->rootNode   = 2;
        }
        
        if ( !( $consKey || $consSec || $login || $password ) )
        {
            die('====================================' ."\n". '=== INI DropBoxConfig not found! ===' ."\n". '====================================');
        }
        
        $this->oauth    = new Dropbox_OAuth_PEAR( $consKey, $consSec );
        $this->dropbox  = new Dropbox_API( $this->oauth );
		$tokens         = $this->dropbox->getToken( $login, $password );
		
        $this->oauth->setToken( $tokens['token'], $tokens['token_secret'] );    	
    }
    
	public static function getInstance()
    {
		if ( self::$instance == null )
        {
			self::$instance = new OCDropbox(); 
		}
        return self::$instance;		
	}
    
    function getBasePath()
    {
        return $this->basePath;
    }

    /**
     * Legge le configurazioni in dropbox.ini
     * Si collega tramite curl e riceve la lista dei file
     *
     * @return array
     * 
     */    
    function getDropboxData($path = '', $directoryContents = true)
    {
		
    	if($path == ''){
	    	$path = $this->basePath; 
    	}
        
        $path = $this->sanitize_path( $path );
    	
        try 
        {
			$info = $this->dropbox->getMetaData( $path, $directoryContents );
            return $info;
        }
        catch ( Exception $e)
        {
            eZDebug::writeError( "Error in ". $path . "\n" . $e . "\n", __METHOD__ );
        	return false;
        }        

    }           

    function getFile($path)
    {
    	$path = $this->sanitize_path( $path );
        $file = $this->dropbox->getFile( $path );
    	return $file;
    }
    
    private function createContainer( $name = 'No name', $parentNodeID = false, $class_identifier = false, $user = false )
    {
        $db = eZDB::instance();
        $db->begin();
        
        if ( !$user )
        {
            $user = eZUser::fetchByName( 'admin' );
            if ( !$user )
            {
                $user = eZUser::currentUser();
            }           
        }
        
        if ( !$parentNodeID )
        {
            $parentNodeID = $this->rootNode;
        }
        $parent_node = eZContentObjectTreeNode::fetch( $parentNodeID );
        
        $attributesData = array(
            'name' =>  $name
        );        
        
        if ( !$class_identifier )
        {
            $class_identifier = isset( $this->mapDropboxFolder[ $name ] ) ? $this->mapDropboxFolder[ $name ] : 'folder';
        }
        
        $params = array(
            'class_identifier'  => $class_identifier,
            'creator_id'        => $user->attribute( 'contentobject_id' ),
            'parent_node_id'    => $parent_node->attribute( 'node_id' ),
            'section_id'        => $parent_node->attribute( 'object' )->attribute( 'section_id' ),
            'attributes'        => $attributesData
        );
    
        $contentObject = eZContentFunctions::createAndPublishObject( $params );
         
        if ( $contentObject )
        {
            eZDebug::writeNotice( "Created Content Object: ID = " .  $contentObject->attribute( 'id' ) . " NAME = " . $contentObject->attribute( 'name' ), __METHOD__  );
        }
        $db->commit();
        return $contentObject;
    }
    
    private function createFile( $dropboxFileData, $parentNodeID, $parentOCDropboxObjectID, $updateDropboxDB = false, $updateEzObject = false )
    {
        $db = eZDB::instance();
        $db->begin();
        
        $fileName = basename( $dropboxFileData['path'] );
        $filePath = $this->varDir . $fileName;
        $h = fopen($filePath,'w');
        $dropboxFileDataPath = $dropboxFileData['path'];
        if($h)
        {
            fwrite($h, $this->getFile($dropboxFileDataPath));
            fclose($h);
        }
        else
        {
            print_r("Error opening " . $filePath . "\n\n");
            $db->commit();
            return false;
        }
        
        $currentUser = eZUser::currentUser();
        $user = eZUser::fetchByName( 'admin' );
        if ( $user ) {
            eZUser::setCurrentlyLoggedInUser( $user, $user->attribute( 'contentobject_id' ) );
        }
        
        $result = array( 'errors' => array() );
        $upload = new eZContentUpload();
        
        if ( $updateEzObject )
        {
            $upload->handleLocalFile( &$result, $filePath, $parentNodeID, $updateEzObject );
        }
        else
        {
            $upload->handleLocalFile( &$result, $filePath, $parentNodeID, false );
        }
        
        eZUser::setCurrentlyLoggedInUser( $currentUser, $currentUser->attribute( 'contentobject_id' ) );
        
        if ( empty( $result['errors']) && !empty( $result['contentobject']) )
        {
            if ( $updateDropboxDB )
            {
                $ocdo = OCDropboxObject::fetchByPath( $dropboxFileData['path'] );
                $ocdo->setAttribute( 'object_id', $result['contentobject']->attribute('id') );
                $ocdo->setAttribute( 'modified', strtotime( $dropboxFileData['modified'] ) );
                $ocdo->store();
            }
            else
            {                
                $ocdo = new OCDropboxObject( array(
                    'parent_id' => $parentOCDropboxObjectID, 
                    'is_dir'    => false,
                    'path'      => $dropboxFileData['path'],
                    'modified'  => strtotime( $dropboxFileData['modified'] ),
                    'object_id' => $result['contentobject']->attribute('id')
                ));
                $ocdo->store();
            }
            $db->commit();
            unlink($filePath);
            return true;
        }
        $db->commit();
        unlink($filePath);
        return false;
    }
    
    private function sanitize_path( $path )
    {
        $parts = explode('/', $path);
        $newPath = '';
        foreach ($parts as $key => $p)
        {
            if ( $key > 0 )
                $newPath .= '/'; 
            $newPath .= rawurlencode($p);
        }
        
        return $newPath;
    }
    
    public function importDropboxData( $cli = false )
    {        
        // current user
        $user = eZUser::fetchByName( 'admin' );
        if ( !$user )
        {
            $user = eZUser::currentUser();
        }
        
        if ( $cli )
        {
            $cli->output( 'Using user: '.$user->attribute( 'login' ) );
        }
        else
        {
            eZDebug::writeNotice( 'Using user: '.$user->attribute( 'login' ), __METHOD__ );
        }
        
        // Dropbox account
        if ( $cli )
        {
            $cli->output( 'Using DropBox account: '. $this->ini->variable( 'DropBoxConfig', 'DropBoxLogin' ) );
            $cli->output( 'Importing from DropBox folder: '. $this->basePath );
        }
        else
        {
            eZDebug::writeNotice( 'Using user: '.$this->ini->variable( 'DropBoxConfig', 'DropBoxLogin' ), __METHOD__ );
            eZDebug::writeNotice( 'Importing from DropBox folder: '. $this->basePath, __METHOD__ );
        }        
        
        // root node
        $rootNode = eZContentObjectTreeNode::fetch( $this->rootNode ); 
        if ( $cli ) 
        {
            $cli->output( 'Import in: '. $rootNode->attribute( 'name' ) . ' (path: ' . $rootNode->attribute( 'path_string' ) . ')' );
        }
        else
        {
            eZDebug::writeNotice( 'Import in: '. $rootNode->attribute( 'name' ) . ' (path: ' . $rootNode->attribute( 'path_string' ) . ')', __METHOD__ );
        }
        
        // get Dropbox data
        $data = $this->getDropboxData();
        if ( isset( $data['contents'] ) )
        {
            foreach( $data['contents'] as $content )
            {
                $this->iterateData($content, 0, $rootNode->attribute('node_id'), $cli);
            }
        }
        
    }
    
    private function iterateData($dropboxItem, $parentDropboxObjectId, $parentNodeID, $cli = false)
    {

        $path = $dropboxItem['path'];
        
        $dropboxItem = $metaData = $this->getDropboxData( $path );
        if ( $cli ) 
        {
            $cli->output( '--> Request meta data for: '. $path );
        }
        else
        {
            eZDebug::writeNotice( 'Request meta data for: '. $path, __METHOD__ );
        }        
        
        if (!$metaData)
        {
            if ( $cli ) 
            {
                $cli->output( 'Not meta data found for: '. $path );
            }
            else
            {
                eZDebug::writeNotice( 'Not meta data found for: '. $path, __METHOD__ );
            }
            return;
        }
        
        if ( $dropboxItem['is_dir'] == 1 )
        {
            $ezNode = false;
            
            // esiste in tabella ezdropbox?
            if ( $dropboxObject = OCDropboxObject::fetchByPath( $path ) )
            {
                // esiste l'oggetto ezcontentobject?
                $ezObject = eZContentObject::fetch( $dropboxObject->attribute( 'object_id' ) );
                if ( $ezObject )
                {
                    $ezNodeId = $ezObject->attribute( 'main_node_id' );                  
                }
                else
                {
                    
                    $parentDropboxObject = OCDropboxObject::fetch( $parentDropboxObjectId );
                    $class_identifier = false;                    
                    if ( $parentDropboxObject )
                    {                        
                        $class_identifier = isset( $this->mapDropboxSubFolder[ basename( $parentDropboxObject->attribute('path') ) ] ) ? $this->mapDropboxSubFolder[ basename( $parentDropboxObject->attribute('path') ) ] : false;
                    }
                    
                    // esiste in tabella ezdropbox ma non in ezcontentobject: uhm qualcuno ha rimosso i nodi... rifacciamoli!                    
                    $ezObject = $this->createContainer( basename( $path ), $parentNodeID, $class_identifier );
                    if ( $ezObject )
                    {
                        if ( $cli ) 
                        {
                            $cli->output( 'RE-Create '. $ezObject->attribute('class_name') .': '. $path );
                        }
                        else
                        {
                            eZDebug::writeNotice( 'RE-Create '. $ezObject->attribute('class_name') .': '. $path, __METHOD__ );
                        }
                        $dropboxObject->setAttribute( 'object_id', $ezObject->attribute('id') );
                        $dropboxObject->store();
                        $ezNodeId = $ezObject->attribute( 'main_node_id' );
                    }
                }
            }
            else
            {
                
                $parentDropboxObject = OCDropboxObject::fetch( $parentDropboxObjectId );
                $class_identifier = false;
                if ( $parentDropboxObject )
                {                    
                    $class_identifier = isset( $this->mapDropboxSubFolder[ basename( $parentDropboxObject->attribute('path') ) ] ) ? $this->mapDropboxSubFolder[ basename( $parentDropboxObject->attribute('path') ) ] : false;
                }                
                
                // non esiste in tabella ezdropbox: creo l'oggetto                
                $ezObject = $this->createContainer( basename( $path ), $parentNodeID, $class_identifier );
                
                if ( $ezObject )
                {
                    if ( $cli ) 
                    {
                        $cli->output( 'Create '. $ezObject->attribute('class_name') .': '. $path );
                    }
                    else
                    {
                        eZDebug::writeNotice( 'Create '. $ezObject->attribute('class_name') .': '. $path, __METHOD__ );
                    }                                        
                    $dropboxObject = new OCDropboxObject( array(
                        'parent_id' => $parentDropboxObjectId, 
                        'is_dir'    => true,
                        'hash'      => $dropboxItem['hash'],
                        'path'      => $dropboxItem['path'],
                        'modified'  => strtotime( $dropboxItem['modified'] ),
                        'object_id' => $ezObject->attribute('id')
                    ));
                    $dropboxObject->store();
                    $ezNodeId = $ezObject->attribute( 'main_node_id' );
                }                
            }

            // itero il contenuto
            if ( isset( $dropboxItem['contents'] ) )
            {
                foreach( $dropboxItem['contents'] as $dropboxSubItem )
                {
                    $this->iterateData($dropboxSubItem, $dropboxObject->attribute('id'), $ezNodeId, $cli);
                }
            }            
        }
        else
        {
            $dropboxObject = OCDropboxObject::fetchByPath( $path );
            if ( !$dropboxObject )
            {
                if ( $this->createFile( $dropboxItem, $parentNodeID, $parentDropboxObjectId ) )
                {                    
                    if ( $cli ) 
                    {
                        $cli->output( 'Import file: '. $path );
                    }
                    else
                    {
                        eZDebug::writeNotice( 'Import file: '. $path, __METHOD__ );
                    }                     
                }
            }
            else
            {
                $ezObject = eZContentObject::fetch( $dropboxObject->attribute( 'object_id' ) );
                if ( !$ezObject )
                {
                    // uhm qualcuno ha rimosso i nodi... rifacciamoli!                    
                    if ( $this->createFile( $dropboxItem, $parentNodeID, $parentDropboxObjectId, true ) )
                    {                        
                        if ( $cli ) 
                        {
                            $cli->output( 'RE-Import file: '. $path );
                        }
                        else
                        {
                            eZDebug::writeNotice( 'RE-Import file: '. $path, __METHOD__ );
                        }                         
                    }
                }
                else
                {
                    // il file è stato modificato? Aggiorno il nodo
                    if ( strtotime( $dropboxItem['modified'] ) > $dropboxObject->attribute( 'modified' ) )
                    {
                        if ( $this->createFile( $dropboxItem, $parentNodeID, $parentDropboxObjectId, true, $ezObject->attribute('main_node') ) )
                        {
                            if ( $cli ) 
                            {
                                $cli->output( 'Update file: '. $path );
                            }
                            else
                            {
                                eZDebug::writeNotice( 'Update file: '. $path, __METHOD__ );
                            }                              
                        }
                    }
                }
            }
        }                
        return;
    }

}

?>
