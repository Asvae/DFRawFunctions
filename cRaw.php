<?php

$wgRaw = array(); 


class cRaw
{	
	public $split_to;
	public $split_from;
	public $ID;
	public $obj;
	public $filename;
	public $text;
	public $tag;
	
	public function __construct ($filename) {
		
		global $wgRawID;
		global $wgRaw;
		$this->filename = $filename;
		
		// load "txt"
		$loaded = $this->loadRaw($filename);
			if ($loaded !== true)
				cMain::getError ('construct: can\'t load raws from file');
		
		// 'falsify' any other public property
		$this->obj = false;
		$this->tag = false;
		$this->split_to = array();
		$this->split_from = false;
		
		// get ID from global
		$this->ID = $wgRawID;
		$wgRawID++;
	}
	
	// Make filename array readable by loadRaw and perform checks
	public static function balanceFilenames(&$filenames = array()) {
		// Check if path exists
		global $wgDFRawPath;
		if (!is_dir($wgDFRawPath))
			return cMain::getError ('Wrong path to file (check $wgDFRawPath in DFRawFunctions.php)');
			
		foreach ($filenames as $i => &$filename) {
			// Balance partial input and load file
			if ($i=0 and count($filename) != 2)
				return cMain::getError ('Specify file folder');
				
			if (count($filename)===2) $filename_count=$i;
			if (count($filename) != 2)
			{
				$filename[1]=$filename[0];
				$filename[0]=$filenames[$filename_count][0];
			}
			$wantfile[$i] = $wgDFRawPath .'/'. $filename[0] .'/'. $filename[1] .'.txt';
		
		if (!is_file($wantfile[$i]))
			return cMain::getError ('Requested file is missing: "'.  $filename[0] .'/'. $filename[1]);
		}
	}
	
	// Load "text" from "filename" array in cRaw instance
	public function loadRaw ($filename = array()) {	
		
		global $wgDFRawPath, $wgRaw;
		if ($wgRaw) foreach ($wgRaw as $raw)
			if ($raw->filename === $filename and !isset($raw->obj))
				return cMain::getError ('loadRaw: cRaw instance text is already defined');
		$wantfile = $wgDFRawPath .'/'. $filename[0] .'/'. $filename[1] .'.txt';
		$this->text = file_get_contents($wantfile);
		$this->filename = $filename;
		
		// Masterwork raw fix
		if ($filename[0] === 'Masterwork')
			cRaw::masterworkRawFix(&$this->text);
		return true;
	}
	
	// Fix corrupted masterwork raws ('!NOFOO!'->'YESFOO[')
	public function masterworkRawFix(&$string) {
	
		$start = 0;
		$words = array();
		$i = 0;
		
		while(true)
		{
			$start = strpos($string,'!NO',$start);
			$end = strpos($string, '!', $start+1);
			
			if ($start === FALSE or $end === FALSE or $end-$start > 30)
				break;
			$words['corrupted'][] = substr($string,$start,$end-$start+1);
			
			$start = $end;
		}
		if($start)
		{
			foreach ($words['corrupted'] as $word)
				$words['fixed'] = "YES".substr($word,3,-1).'[';
		
			$string = str_replace($words['corrupted'], $words['fixed'], $string);
		}
	}
	
	// Search for object in "text" of cRaw instance
	public function getObject () {
	
		if ($this->text === false){
			print_r($this);
			return cMain::getError ('getObject: "text" property is not defined');
			}
		$start = strpos($this->text, '[OBJECT:') + 8;
		$end = strpos($this->text, ']', $start + 1);
		if (!isset($start, $end))
			return cMain::getError ('Object is not found');
		$this->obj = substr($this->text, $start, $end - $start);
			return true;
			
	}
	 
	// make "tag" array from "text" string in cRaw instance
	public function getTags () {
	
		if ($this->tag !== false)
			return cMain::getError ("getTags: \"tag\" property of cRaw instance is already defined");
		
		if ($this->text === false)
			return cMain::getError ('getTags: "text" property of cRaw instance is missing');
		
		$raws = array();
		$off = 0;
		
		while (1)
		{
			$start = strpos($this->text, '[', $off);
			if ($start === FALSE)
				break;
			$end = strpos($this->text, ']', $start);
			if ($end === FALSE)
				break;
			if ($off < $start)
			{
				$tmp = explode("\n", trim(substr($this->text, $off, $start - $off), "\r\n"));
			}
			$tag = explode(':', substr($this->text, $start + 1, $end - $start - 1));
			$raws[] = $tag;
			$off = $end + 1;
		}
		$this->tag = $raws;
		return false;
	}
	
	// splits object (if "tag" and "obj" are present) into 
	// smaller objects (different "tag", "obj", "ID"; same "filename")
	public function split_by_object () {
		if ($this->split_to or $this->split_from)
			return true;
		if ($this->obj === false)
			return cMain::getError ('split_by_object: "obj" property of cRaw instance is missing');
		if ($this->tag === false)
			return cMain::getError ('split_by_object: "tag" property of cRaw instance is missing');
			
		$object = cRaw::objectCheck($this->obj);
		
		global $wgRaw; 
		global $wgRawID;
		$object_present = false;
		foreach  ($this->tag as $tag) {
		
			$is_object = in_array($tag[0], $object);

			if ($is_object and $object_present === false) {
				$object_present = true;
			}
			if ($is_object and $object_present === true) {
				$ID = $wgRawID;
				$wgRaw[$ID] = new cRaw($this->filename);
				$wgRaw[$ID]->obj = $tag;
				$wgRaw[$ID]->tag = array();
				$wgRaw[$ID]->split_from = $this->ID;
				$this->split_to[] = $ID;
				
			}
			if (!$is_object and $object_present === true)
			$wgRaw[$ID]->tag[] = $tag;
		}
		return true;
	
	}
	
	// make array from object, allowing comparison with actual tags
	// source: http://dwarffortresswiki.org/index.php/DF2012:Raw_file
	public static function objectCheck ($obj = '') {
		switch ($obj) {
			case 'BUILDING':
				$compare = array ('BUILDING_FURNACE', 'BUILDING_WORKSHOP');
			break;
			case 'LANGUAGE': 
				$compare = array ('SYMBOL', 'WORD');
			break;
			case 'ITEM': 
				$compare = explode (', ','TEM_AMMO, ITEM_ARMOR, ITEM_FOOD, ITEM_GLOVES, ITEM_HELM, ITEM_INSTRUMENT, ITEM_PANTS, ITEM_SHIELD, ITEM_SHOES, ITEM_SIEGEAMMO, ITEM_TOOL, ITEM_TOY, ITEM_TRAPCOMP, ITEM_WEAPONSYMBOL');
			break;
			case 'LANGUAGE': 
				$compare = array ('SYMBOL', 'WORD');
			break;
			default:
				$compare = array ($obj);
			break;
		}
		return $compare;
	}
	
	// returns ID if cRaw with property/value is present
	public static function loaded_raw_check ($property, $value) {
		if (!isset($property, $value))
			return cMain::getError ('loaded_raw_check: define property and value');
		global $wgRaw;
		if ($wgRaw) foreach ($wgRaw as $raw)
			if (!isset($raw->$property) and $raw->$property === $value)
				return $raw->ID;
		return false;
	}
}