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
    private $accountInfo;
    
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
        
        $this->varDir = eZSys::cacheDirectory() . ezSys::fileSeparator() . eZINI::instance()->variable( 'Cache_dropbox', 'path' ) . ezSys::fileSeparator();
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
        
        if ( !( $consKey || $consSec ) )
        {
            $this->log( 'INI DropBoxConfig not found!' );
            eZExecution::cleanExit();
        }
        
        $dropboxToken = eZSiteData::fetchByName( 'dropbox_token' );
        if ( !$dropboxToken )
        {
            $this->log( 'Authorize first: visit your <your_site_backend>/dropbox/dashboard' );
            eZExecution::cleanExit();
        }
        
        $token = unserialize( $dropboxToken->attribute( 'value' ) );        
        
        $encrypter = new \Dropbox\OAuth\Storage\Encrypter( md5( 'ocdropbox' ) );
        $storage = new \Dropbox\OAuth\Storage\Session( $encrypter );
        $storage->set( $token, 'access_token' );        
        $OAuth = new \Dropbox\OAuth\Consumer\Curl( $consKey, $consSec, $storage );
        $this->dropbox = new \Dropbox\API( $OAuth );
        $this->dropbox->setResponseFormat( 'php' );
        $this->accountInfo = $this->dropbox->accountInfo();
        
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
    function getDropboxData($path = '')
    {
		
    	if ( $path == '' ){
	    	$path = $this->basePath; 
    	}
        
        try 
        {
			$info = $this->dropbox->metaData( $path );
            //eZCLI::instance()->error( var_export( $info,1 ) );
            return $info['body'];
        }
        catch ( Exception $e)
        {
            $this->log( "Error in <". $path . ">: " . $e->getMessage() . "\n", 'error', __METHOD__ );
        	return false;
        }        

    }           

    function getFile( $path )
    {
        $file = $this->dropbox->getFile( $path );
    	return $file['data'];
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
            $this->log( "Created Content Object: ID = " .  $contentObject->attribute( 'id' ) . " NAME = " . $contentObject->attribute( 'name' ), 'notice', __METHOD__  );
        }
        $db->commit();
        return $contentObject;
    }
    
    private function createFile( $dropboxFileData, $parentNodeID, $parentOCDropboxObjectID, $updateDropboxDB = false, $updateEzObject = false )
    {
        $db = eZDB::instance();
        $db->begin();

        $fileName = basename( $dropboxFileData->path );
        $filePath = $this->varDir . $fileName;
        $dropboxFileDataPath = $dropboxFileData->path;
        
        if ( !eZFile::create( $fileName, $this->varDir, $this->getFile( $dropboxFileDataPath ) ) )        
        {
            $this->log( "Error opening " . $filePath, 'error', __METHOD__ );
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
        
        try
        {
            if ( $updateEzObject )
            {
                $upload->handleLocalFile( $result, $filePath, $parentNodeID, $updateEzObject );
            }
            else
            {
                $upload->handleLocalFile( $result, $filePath, $parentNodeID, false );
            }
        }
        catch( Exception $e )
        {            
            $this->log( 'Error importing ' . $filePath . ': ' . $e->getMessage(), 'error', __METHOD__ );
            $db->commit();
            unlink($filePath);
            return false;
        }
        
        eZUser::setCurrentlyLoggedInUser( $currentUser, $currentUser->attribute( 'contentobject_id' ) );
        
        if ( empty( $result['errors'] ) && !empty( $result['contentobject']) )
        {
            if ( $updateDropboxDB )
            {
                $ocdo = OCDropboxObject::fetchByPath( $dropboxFileData->path );
                $ocdo->setAttribute( 'object_id', $result['contentobject']->attribute('id') );
                $ocdo->setAttribute( 'modified', strtotime( $dropboxFileData->modified ) );
                $ocdo->store();
            }
            else
            {                
                $ocdo = new OCDropboxObject( array(
                    'parent_id' => $parentOCDropboxObjectID, 
                    'is_dir'    => false,
                    'path'      => $dropboxFileData->path,
                    'modified'  => strtotime( $dropboxFileData->modified ),
                    'object_id' => $result['contentobject']->attribute('id')
                ));
                $ocdo->store();
            }
            $db->commit();
            //unlink($filePath);
            return true;
        }
        else
        {
            $errors = array();
            if ( !empty( $result['errors'] ) )
            {
                foreach( $result['errors'] as $error )
                {
                    $errors[] = $error['description'];
                }
            }
            $this->log( 'Error importing ' . $filePath . ': ' . implode( ' ', $errors ), 'error', __METHOD__ );
            $db->commit();
            unlink($filePath);
            return false;
        }
        
    }
    
    public function importDropboxData()
    {        
        // current user
        $user = eZUser::fetchByName( 'admin' );
        if ( !$user )
        {
            $user = eZUser::currentUser();
        }
        
        $this->log( 'Using user: '.$user->attribute( 'login' ), 'notice', __METHOD__ );
        $this->log( 'Using DropBox account: '. $this->accountInfo['body']->display_name, 'notice', __METHOD__ ); 
        $this->log( 'Importing from DropBox folder: '. $this->basePath, 'notice', __METHOD__ );
        
        // root node
        $rootNode = eZContentObjectTreeNode::fetch( $this->rootNode );
        if ( !$rootNode )
        {
            $this->log( "Root node $this->rootNode do not exist", 'error' );
            eZExecution::cleanExit();
        }
        $this->log( 'Import in: '. $rootNode->attribute( 'name' ) . ' (path: ' . $rootNode->attribute( 'path_string' ) . ')', 'notice', __METHOD__ );
        
        // get Dropbox data
        $data = $this->getDropboxData();        
        if ( isset( $data->contents ) )
        {
            foreach( $data->contents as $content )
            {
                $this->iterateData($content, 0, $rootNode->attribute('node_id') );
            }
        }
        
    }
    
    private function iterateData($dropboxItem, $parentDropboxObjectId, $parentNodeID )
    {

        $path = $dropboxItem->path;
        
        $dropboxItem = $metaData = $this->getDropboxData( $path );
        $this->log( '--> Request meta data for: '. $path, 'notice', __METHOD__ );
        
        if (!$metaData)
        {
            $this->log( 'Not meta data found for: '. $path, 'error', __METHOD__ );
            return;
        }
        
        if ( $dropboxItem->is_dir == 1 )
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
                        $this->log( 'RE-Create '. $ezObject->attribute('class_name') .': '. $path, 'notice', __METHOD__ );
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
                    $this->log( 'Create '. $ezObject->attribute('class_name') .': '. $path, 'notice', __METHOD__ );
                    
                    $dropboxObject = new OCDropboxObject( array(
                        'parent_id' => $parentDropboxObjectId, 
                        'is_dir'    => true,
                        'hash'      => $dropboxItem->hash,
                        'path'      => $dropboxItem->path,
                        'modified'  => strtotime( $dropboxItem->modified ),
                        'object_id' => $ezObject->attribute('id')
                    ));
                    $dropboxObject->store();
                    $ezNodeId = $ezObject->attribute( 'main_node_id' );
                }                
            }

            // itero il contenuto
            if ( isset( $dropboxItem->contents ) )
            {
                foreach( $dropboxItem->contents as $dropboxSubItem )
                {
                    $this->iterateData($dropboxSubItem, $dropboxObject->attribute('id'), $ezNodeId );
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
                    $this->log( 'Import file: '. $path, 'notice', __METHOD__ );
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
                        $this->log( 'RE-Import file: '. $path, 'notice', __METHOD__ );                        
                    }
                }
                else
                {
                    // il file è stato modificato? Aggiorno il nodo
                    if ( strtotime( $dropboxItem->modified ) > $dropboxObject->attribute( 'modified' ) )
                    {
                        if ( $this->createFile( $dropboxItem, $parentNodeID, $parentDropboxObjectId, true, $ezObject->attribute('main_node') ) )
                        {
                            $this->log( 'Update file: '. $path, 'notice', __METHOD__ );
                        }
                    }
                    else
                    {
                        $this->log( 'File not modified', 'notice', __METHOD__ );
                    }
                }
            }
        }                
        return;
    }
    
    function log( $message, $level = 'notice', $method = false )
    {
        $cli = eZCLI::instance();
        switch( $level )
        {
            case 'error':
            {
                eZDebug::writeError( $message, $method );
                $cli->error( $message );
            } break;            
            case 'warning':
            {
                eZDebug::writeWarning( $message, $method );
                $cli->warning( $message );
            } break;
            default:
            {
                eZDebug::writeNotice( $message, $method );
                $cli->notice( $message );
            } break;
        }
    }

}

?>
