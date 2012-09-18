<?php
/**
* @copyright	Copyright (C) 2009 - 2009 Ready Bytes Software Labs Pvt. Ltd. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
* @package		PayPlans
* @subpackage	Frontend
* @contact 		shyam@readybytes.in
*/
if(defined('_JEXEC')===false) die();

class XiAbstractJ15Controller extends XiAbstractControllerBase
{
	public function getRedirect()
	{
		return $this->_redirect;
	}	
	public function getMessage()
	{
		return $this->_message;
	}
	
	public function getdoTask()
	{
		return $this->_doTask;
	}
	
	public function setdoTask($doTask)
	{
		$this->_doTask = $doTask;
		return $this;		
	}
	
	public function getTask()
	{
		return $this->_task;
	}
	
	public function setTask($task)
	{
		$this->_task = $task;
		return $this;		
	}
}

class XiAbstractController extends XiAbstractJ15Controller
{}