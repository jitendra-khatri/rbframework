<?php
/**
* @copyright	Copyright (C) 2009 - 2012 Ready Bytes Software Labs Pvt. Ltd. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
* @package		RB Framework
* @subpackage	Frontend
* @contact 		shyam@readybytes.in
*/
if(defined('_JEXEC')===false) die();

abstract class Rb_AbstractModel extends Rb_AdaptModel
{
	protected 	$_pagination		= '';
	protected	$_query				= null;
	protected 	$_total 			= array();
	protected 	$_records 			= array();
	public	$_component			= '';
	protected	$_form				= null;

	public function __construct($options = array())
	{
		//name can be collected by parent class
		if(array_key_exists('name',$options)==false)
			$options['name']	= $this->getName();

		if(array_key_exists('prefix',$options)==false)
			$options['prefix']	= $this->getPrefix();

		//now construct the parent
		parent::__construct($options);

		//at least know where we are, any time
		$this->_context	=JString::strtolower($options['prefix'].'.Model.'.$options['name']);
	}

	/*
	 * We want to make error handling to common objects
	 * So we override the functions and direct them to work
	 * on a global error object
	 */
	public function getError($i = null, $toString = true )
	{
		$errObj	=	Rb_Factory::getErrorObject();
		return $errObj->getError($i, $toString);
	}

	public function setError($errMsg)
	{
		$errObj	=	Rb_Factory::getErrorObject();
		return $errObj->setError($errMsg);
	}

	/*
	 * Returns a string telling where are you.
	 */
	public function getContext()
	{
		return strtolower($this->_component.'.'.$this->getName());
	}
	
	/*
	 * We need to override joomla behaviour as they differ in
	 * Model and Controller Naming
	 * In Joomla   -> JModelProducts, JProductsController
	 * In PayPlans -> PayplansModelProducts, PayplansControllerProducts
	 */
	function getName()
	{
		$name = $this->_name;

		if (empty( $name ))
		{
			$r = null;
			if (!preg_match('/Model(.*)/i', get_class($this), $r)) {
				JError::raiseError (500, "Rb_Model::getName() : Can't get or parse class name.");
			}
			$name = strtolower( $r[1] );
		}

		return $name;
	}

	/*
	 * Collect prefix auto-magically
	 */
	public function getPrefix()
	{
		if(isset($this->_prefix) && empty($this->_prefix)===false)
			return $this->_prefix;

		$r = null;
		Rb_Error::assert(preg_match('/(.*)Model/i', get_class($this), $r), Rb_Text::sprintf('PLG_SYSTEM_RBSL_ERROR_XIMODEL_GETPREFIX_CANT_GET_OR_PARSE_CLASSNAME', get_class($this)), Rb_Error::ERROR);

		$this->_prefix  =  JString::strtolower($r[1]);
		return $this->_prefix;
	}


	/**
	 * Returns the Query Object if exist
	 * else It builds the object
	 * @return Rb_Query
	 */
	public function getQuery()
	{
		//query already exist
		if($this->_query){
			return $this->_query;
		}

		//create a new query
		$this->_query = new Rb_Query();

		// Query builder will ensure the query building process
		// can be overridden by child class
		if($this->_buildQuery($this->_query)){
			return $this->_query;
		}

		//in case of errors return null
		throw new Exception('Not Able to build query');
	}
	
	public function clearQuery()
	{
		$this->_query = null;
	}

	/*
	 * Count number of total records as per current query
	 * clean the query element
	 */
	public function getTotal($queryClean = array('select','limit','order'))
	{
		if($this->_total){
			return $this->_total;
		}

		$query 	= $this->getQuery();

		//Support query cleanup
		$tmpQuery = clone($query);

		foreach($queryClean as $clean){
			$tmpQuery->clear(JString::strtolower($clean));
		}

		$tmpQuery->select('COUNT(*)');
        $this->_total 	= $tmpQuery->dbLoadQuery()->loadResult();

		return $this->_total;
	}

	/**
	 * @return Rb_Pagination
	 */
	function &getPagination()
	{
	 	if($this->_pagination)
	 		return $this->_pagination;

		$this->_pagination = new Rb_Pagination($this);
		return $this->_pagination;
	}


	public function _populateGenericFilters(Array &$filters=array())
	{
		$table = $this->getTable();
		if(!$table)
			return;

		$vars = $table->getProperties();
		$app  = Rb_Factory::getApplication();

		$data = array();
		$context = $this->getContext();

		foreach($vars as $k => $v)
		{
			$filterName  = "filter_{$context}_{$k}";
			$oldValue= $app->getUserState($filterName);
			$value = $app->getUserStateFromRequest($filterName ,$filterName);
			
			//offset is set to 0 in case previous value is not equals to current value
			//otherwise it will filter according to the pagination offset
			if(!empty($oldValue) && $oldValue != $value){
				$filters['limitstart']=0;
			}

			$data[$k] = $value;
		}

		$filters[$context] = $data;

		return;
	}


	/**
	 * Get an object of model-corresponding table.
	 * @return Rb_Table
	 */
	public function getTable($tableName=null)
	{
		// support for parameter
		if($tableName===null)
			$tableName = $this->getName();

		$table	= Rb_Factory::getInstance($tableName,'Table',$this->_component);
		if(!$table)
			$this->setError(Rb_Text::_('NOT_ABLE_TO_GET_INSTANCE_OF_TABLE'.':'.$this->getName()));

		return $table;
	}

	/**
	 * @return int
	 */
	public function getId()
	{
		return $this->getState('id') ;
	}
}

abstract class Rb_Model extends Rb_AbstractModel
{

	public $filterMatchOpeartor = array();
	
	public function getEmptyRecord()
	{
		$vars = $this->getTable()->getProperties();
		$retObj = new stdClass();

		foreach($vars as $key => $value)
			$retObj->$key = null;

		return array($retObj);
	}
	/*
	 * Returns Records from Model Tables
	 * as per Model STATE
	 */
	public function loadRecords(Array $queryFilters=array(), Array $queryClean = array(), $emptyRecord=false, $orderby = null)
	{
		$query = $this->getQuery();

		//there might be no table and no query at all
		if($query === null )
			return null;

		//Support Query Filters, and query cleanup
		$tmpQuery = clone ($query);

		foreach($queryClean as $clean){
			$tmpQuery->clear(JString::strtolower($clean));
		}

		foreach($queryFilters as $key=>$value){
			//support id too, replace with actual name of key
			$key = ($key==='id')? $this->getTable()->getKeyName() : $key;
			
			// only one condition for this key
			if(is_array($value)==false){
				$tmpQuery->where("`tbl`.`$key` =".$this->_db->Quote($value));
				continue;
			}
			
			// multiple keys are there
			foreach($value as $condition){
				
				// not properly formatted
				if(is_array($condition)==false){
					continue;
				}
				
				// first value is condition, second one is value
				list($operator, $val)= $condition;
				$tmpQuery->where("`tbl`.`$key` $operator ".$val);
			}
			
		}

		if($orderby === null){
			$orderby = $this->getTable()->getKeyName();
		}
		
		//we want returned record indexed by columns
		$this->_recordlist = $tmpQuery->dbLoadQuery()
		 							  ->loadObjectList($orderby);

		//handle if some one required empty records, only if query records were null
		if($emptyRecord && empty($this->_recordlist)){
			$this->_recordlist = $this->getEmptyRecord();
		}

		return $this->_recordlist;
	}


	/**
	 * This should vaildate and filter the data
	 * @param unknown_type $data
	 * @param unknown_type $pk
	 * @param array $filter
	 * @param array $ignore
	 */
	function validate(&$data, $pk=null,array $filter = array(),array $ignore = array())
	{
		return true;
	}


	/**
	 * Save given data for the given record
	 * @param array $data : date to be saved
	 * @param int/string $pk : the record ID, if 0 given data will be saved as new record
	 * @param boolean $new : is a new record (then we will not load it from table) 
	 */
	function save($data, $pk=null, $new=false)
	{
		if(isset($data)===false || count($data)<=0)
		{
			$this->setError(Rb_Text::_('PLG_SYSTEM_RBSL_NO_DATA_TO_SAVE'));
			return false;
		}

		//try to calculate automatically
		 if($pk === null)
			$pk = (int) $this->getId();

		//also validate via model
		if($this->validate($data, $pk)===false)
		{
			//$this->setError(Rb_Text::_("FIELDS VALUE ARE NOT VALIDATE"));
			//$this->setError(Rb_Factory::getErrorObject()->setError())
			return false;
		}

		// resolve parameter type variables
		//$this->resolveParameters($data);

		//load the table row
		$table = $this->getTable();
		if(!$table){
			$this->setError(Rb_Text::_('PLG_SYSTEM_RBSL_TABLE_DOES_NOT_EXIST'));
			return false;
		}
		// Bug #29
		// If table object was loaded by some code previously
		// then it can overwrite the previous record
		// So we must ensure that either PK is set to given value
		// Else it should be set to 0
		$table->reset(true);

		//it is a NOT a new record then we MUST load the record
		//else this record does not exist
		if($pk && $new===false && $table->load($pk)===false){
			$this->setError(Rb_Text::_('PLG_SYSTEM_RBSL_NOT_ABLE_TO_LOAD_ITEM'));
			return false;
		}

		//bind, and then save
		//$myData = $data[$this->getName()][$pk===null ? 0 : $pk];
	    if($table->bind($data) && $table->save($new))
	    {
	    	// We should return the record's ID rather then true false
			return $table->{$table->getKeyName()};
	    }

		//some error occured
		$this->setError($table->getError());
		return false;
	}

	/**
	 * Method to delete rows.
	 */
	public function delete($pk=null)
	{
		//load the table row
		$table = $this->getTable();

		if(!$table)
			return false;

		//try to calculate automatically
		 if($pk === null){
			$pk = (int) $this->getId();
		 }

		//if we have itemid then we MUST load the record
		// else this is a new record
		if(!$pk)
		{
			$this->setError(Rb_Text::_('PLG_SYSTEM_RBSL_NO_ITEM_ID_AVAILABLE_TO_DELETE'));
			return false;
		}

		//try to delete
	    if($table->delete($pk)){
	    	return true;
	    }

		//some error occured
		$this->setError($table->getError());
		return false;
	}

	/**
	 * Method to delete more than one rows according to given condition and glue.
	 */
	public function deleteMany($condition, $glue='AND', $operator='=')
	{
		// assert if invalid condition
		Rb_Error::assert(is_array($condition), Rb_Text::_('PLG_SYSTEM_RBSL_ERROR_INVALID_CONDITION_TO_DELETE_DATA'));
		Rb_Error::assert(!empty($condition), Rb_Text::_('PLG_SYSTEM_RBSL_ERROR_INVALID_CONDITION_TO_DELETE_DATA'));

		$query = new Rb_Query();
		$query->delete()
				->from($this->getTable()->getTableName());

		foreach($condition as $key => $value)
			$query->where(" $key $operator '$value' ", $glue);

		return $query->dbLoadQuery()->query();
	}

	/**
	 * RBFW_TODO Method to order rows.
	 */
	public function order($pk, $change)
	{
		//load the table row
		$table = $this->getTable();

		if(!$table)
			return false;

		//try to calculate automatically
		 if($pk == null)
			$pk = (int) $this->getId();

		//if we have itemid then we MUST load the record
		// else this is a new record
		if(!$pk)
		{
			$this->setError(Rb_Text::_('PLG_SYSTEM_RBSL_ERROR_NO_ITEM_ID_AVAILABLE_TO_CHANGE_ORDER'));
			return false;
		}

		//try to move
	    if($table->load($pk) && $table->move($change))
			return true;

		//some error occured
		$this->setError($table->getError());
		return false;
	}

	/**
	 * RBFW_TODO Method to switch boolean column values.
	 */
	public function boolean($pk, $column, $value, $switch)
	{
		//load the table row
		$table = $this->getTable();

		if(!$table)
			return false;

		//try to calculate automatically
		 if($pk === null)
			$pk = (int) $this->getId();

		//if we have itemid then we MUST load the record
		if(!$pk)
		{
			$this->setError(Rb_Text::_('PLG_SYSTEM_RBSL_NO_ITEM_ID_AVAILABLE_TO_CHANGE_ORDER'));
			return false;
		}

		//try to switch
	    if($table->load($pk) && $table->boolean($column, $value, $switch))
			return true;

		//some error occured
		$this->setError($table->getError());
		return false;
	}

	/* Child classes should not overload it */
	final public function _buildQuery(Rb_Query &$query=null)
    {
    	static $functions = array('Fields','From','Joins','Where','Group','Order','Having');

    	$table	= $this->getTable();
    	if(!$table)	{
    		throw new Exception('Table does not exist for model'.$this->getName());
    	}

    	if($query === null){
    		$query = $this->getQuery();
    	}

    	foreach($functions as $func)
    	{
    		$functionName = "_buildQuery$func";
    		$this->$functionName($query);
    	}

    	// if working for individual record then no need to add limit
    	if(!$this->getId()){
    	 	$this->_buildQueryLimit($query);
    	}
    	 
	    return true;
    }


    protected function _buildQueryFields(Rb_Query &$query)
    {
		$query->select('tbl.*');
    }

	/**
     * Builds FROM tables list for the query
     */
    protected function _buildQueryFrom(Rb_Query &$query)
    {
    	$name = $this->getTable()->getTableName();
    	$query->from($name.' AS tbl');
    }

    /*
     * Every entity should define this function, as they need to
     * join with fields table
     */
    protected function _buildQueryJoins(Rb_Query &$query)
    {

    }

    // RBFW_TODO : Remove this final keword, and break up filter
    final protected function _buildQueryWhere(Rb_Query &$query)
    {
    	//get generic filter and fix it
    	$filters = $this->getState($this->getContext());
        
    	if(is_array($filters)===false)
    		return;

		foreach($filters as $key=>$value){
			if($value === null)
				continue;
			

			$this->_buildQueryFilter($query, $key, $value);
		}
		return;
    }

    protected function _buildQueryFilter(Rb_Query &$query, $key, $value)
    {
    	// Only add filter if we are working on bulk reocrds
		if($this->getId()){
			return $this;
		}
		
    	Rb_Error::assert(isset($this->filterMatchOpeartor[$key]), "OPERATOR FOR $key IS NOT AVAILABLE FOR FILTER");
    	Rb_Error::assert(is_array($value), Rb_Text::_('PLG_SYSTEM_RBSL_VALUE_FOR_FILTERS_MUST_BE_AN_ARRAY'));

    	$cloneOP    = $this->filterMatchOpeartor[$key];
    	$cloneValue = $value;
    	
    	while(!empty($cloneValue) && !empty($cloneOP)){
    		$op  = array_shift($cloneOP);
    		$val = array_shift($cloneValue);

			// discard empty values
    		if(!isset($val) || '' == JString::trim($val))
    			continue;

    		if(JString::strtoupper($op) == 'LIKE'){
	    	  	$query->where("`tbl`.`$key` $op '%{$val}%'");
				continue;
	    	}

    		$query->where("`tbl`.`$key` $op '$val'");
	    		
    	}
    }
    
    protected function _buildQueryGroup(Rb_Query &$query)
    {}

    /**
     * Builds a generic ORDER BY clasue based on the model's state
     */
    protected function _buildQueryOrder(Rb_Query &$query)
    {
		$order      = $this->getState('filter_order');
       	$direction  = strtoupper($this->getState('filter_order_Dir'));

    	if($order)
    		$query->order("$order $direction");

		if (array_key_exists('ordering', $this->getTable()->getFields()))
			$query->order('ordering ASC');
    }

    protected function _buildQueryHaving(Rb_Query &$query)
    {}
    
 	protected function _buildQueryLimit(Rb_Query &$query)
 	{
		$limit       = $this->getState('limit');
       	$limitstart  = $this->getState('limitstart');

       	if($limit){
       		$query->limit($limit, $limitstart);
       	}
       	return;
    }
}