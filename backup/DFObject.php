<?php

class DFObject
{	
	public $objectID;
	
	function __construct($ID) {
		if (is_numeric($ID))
			$this->objectID = $ID.'+';
		
	}
	
	public function getID ()
	{
		return $this->objectID;
	}
}