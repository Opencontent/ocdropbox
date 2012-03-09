<?php

/**
 * OCDropboxObject class inherits eZPersistentObject class
 * to be able to access eztags database table through API
 * 
 */
class OCDropboxObject extends eZPersistentObject
{
    /**
     * Constructor
     * 
     */
    function __construct( $row )
    {
        parent::__construct( $row );
    }

    /**
     * Returns the definition array for OCDropboxObject
     * 
     * @return array
     */
    static function definition()
    {
        return array( 'fields' => array( 'id' => array( 'name' => 'ID',
                                                        'datatype' => 'integer',
                                                        'default' => 0,
                                                        'required' => true ),
                                         'parent_id' => array( 'name' => 'ParentID',
                                                             'datatype' => 'integer',
                                                             'default' => 0,
                                                             'required' => false ),
                                         'is_dir' => array( 'name' => 'IsDir',
                                                             'datatype' => 'integer',
                                                             'default' => 0,
                                                             'required' => false ),                                         
                                         'hash' => array( 'name' => 'Hash',
                                                             'datatype' => 'string',
                                                             'default' => ''),
                                         'path' => array( 'name' => 'Path',
                                                             'datatype' => 'string',
                                                             'default' => '',                                                             
                                                             'required' => false ),                    
                                         'modified' => array( 'name' => 'Modified',
                                                             'datatype' => 'integer',
                                                             'default' => 0,
                                                             'required' => false ),
                                         'object_id' => array( 'name' => 'ObjectID',
                                                             'datatype' => 'integer',
                                                             'default' => 0,
                                                             'required' => true )),                     
                      'function_attributes' => array( 'name' => 'Name', 'parent' => 'getParent', 'is_container' => 'isDir', 'children' => 'children' ),
                      'keys' => array( 'id' ),
                      'increment_key' => 'id',
                      'class_name' => 'OCDropboxObject',
                      'sort' => array( 'path' => 'asc', 'modified' => 'desc' ),
                      'name' => 'ezdropbox' );
    }

    /**
     * Returns content parent (directory)
     * 
     * @return OCDropboxObject
     */
	function getParent()
	{
		return eZPersistentObject::fetchObject( OCDropboxObject::definition(), null, array('id' => $this->ParentID) );
	}

    /**
     * Returns true if is a directory
     * 
     * @return bool
     */
	function isDir()
	{
		return $this->IsDir;
	}

	function Name()
	{
		return basename( $this->Path );
	}

    /**
     * Returns array of OCDropboxObject objects for given parent ID
     * 
     * @return array
     */
	function children()
	{
		return self::fetchByParentID($this->ID);
	}

    /**
     * Returns weather content has a parent (directory)
     * 
     * @return bool
     */
	function hasParent()
	{
		return eZPersistentObject::count( OCDropboxObject::definition(), array('id' => $this->ParentID, 'is_dir' => 1) );
	}
    
    
    /**
     * Store data with ObjectID field check
     * 
     */    
    function store( $fieldFilters = null )
    {
        if ( $this->ObjectID == 0 )
        {
             eZDebug::writeError( "Not ObjectID found", __METHOD__ );
             return;
        }
        self::storeObject( $this, $fieldFilters );
    } 

    /**
     * Returns OCDropboxObject for given ID
     * 
     * @static
     * @param integer $id
     * @return OCDropboxObject
     */
	static function fetch($id)
	{
		return eZPersistentObject::fetchObject( OCDropboxObject::definition(), null, array('id' => $id) );
	}

    /**
     * Returns array of OCDropboxObject objects for given parent ID
     * 
     * @static
     * @param integer $parentID
     * @return array
     */	
	static function fetchByParentID($parentID)
	{
		return eZPersistentObject::fetchObjectList( OCDropboxObject::definition(), null, array('parent_id' => $parentID) );
	}

    /**
     * Returns count of OCDropboxObject objects for given parent ID
     * 
     * @static
     * @param integer $parentID
     * @return integer
     */	
	static function childrenCountByParentID($parentID)
	{
		return eZPersistentObject::count( OCDropboxObject::definition(), array('parent_id' => $parentID) );
	}

    /**
     * Returns array of OCDropboxObject objects for given fetch parameters
     * 
     * @static
     * @param mixed $param
     * @return OCDropboxObject
     */		
	static function fetchByHash($param)
	{		
        return eZPersistentObject::fetchObject( OCDropboxObject::definition(), null, array('hash' => $param) );
	}   

    /**
     * Returns array of OCDropboxObject objects for given fetch parameters
     * 
     * @static
     * @param mixed $param
     * @return OCDropboxObject
     */		
	static function fetchByPath($param)
	{
        return eZPersistentObject::fetchObject( OCDropboxObject::definition(), null, array('path' => $param) );
	}  

}

?>
