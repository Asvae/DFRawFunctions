<?php

class cRaw
{	
	public $obj;
	public $text;
	public $filename;
	
	public function __construct () {
		$this->obj = false;
		$this->filename = false;
		$this->text = '';
	}
	
	public static function balanceFilenames(&$filenames = array()) {
		// Check if path exists
		global $wgDFRawPath;
		if (!is_dir($wgDFRawPath))
			return '<span class="error">Wrong path to file (check $wgDFRawPath in DFRawFunctions.php).</span>';
			
		foreach ($filenames as $i => &$filename) {
			// Balance partial input and load file
			if ($i=0 and count($filename) != 2)
				return '<span class="error">Specify file folder.</span>';
				
			if (count($filename)===2) $filename_count=$i;
			if (count($filename) != 2)
			{
				$filename[1]=$filename[0];
				$filename[0]=$filenames[$filename_count][0];
			}
			$wantfile[$i] = $wgDFRawPath .'/'. $filename[0] .'/'. $filename[1] .'.txt';
		
		if (!is_file($wantfile[$i]))
			return '<span class="error">Requested file is missing: "'.  $filename[0] .'/'. $filename[1] .'".</span>';
		}
	}
	
	public function loadRaw ($filename = array()) {	
		
		global $wgDFRawPath, $wgRaw;
		foreach ($wgRaw as $raw)
			if ($raw->filename === $filename and !isset($raw->obj))
				return false;
		$wantfile = $wgDFRawPath .'/'. $filename[0] .'/'. $filename[1] .'.txt';
		$this->text = file_get_contents($wantfile);
		$this->filename = $filename;
		
		// Masterwork raw fix
		if ($filename[0] === 'Masterwork')
			cRaw::masterworkRawFix(&$this->text);
		return true;
	}
	
	// Fix corrupted masterwork raws
	public function masterworkRawFix(&$string) {
	
		$start=0;
		$words=array();
		$i=0;
		
		while(true)
		{
			$start = strpos($string,'!NO',$start);
			$end = strpos($string, '!', $start+1);
			
			if ($start === FALSE or $end === FALSE or $end-$start > 30)
				break;
			$words['corrupted'][] = substr($string,$start,$end-$start+1);
			
			$start=$end;
		}
		if($start)
		{
			foreach ($words['corrupted'] as $word)
				$words['fixed']="YES".substr($word,3,-1).'[';
		
			$string=str_replace($words['corrupted'], $words['fixed'], $string);
		}
	}
	
}