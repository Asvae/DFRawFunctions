<?php
// Globals
{
ini_set('display_errors', 'On');
error_reporting(E_ALL);
}

class cMain {
	
	public static function dfMain (&$parser, $type = '', $filename = '', $object = '')
	{
		global $wgRawPath, $wgRawID, $wgRaw, $wgNoWiki, $wgError
		if (!isset($wgRawID))
			$wgRawID = 1;
		$wgNoWiki = 0;
		$output = '';
		
		// Load file, define object
		if !
		if (isset($in['filename']))	{
			
			global $wgDFRawEnableDisk;
			if (!$wgDFRawEnableDisk === true) {
				return cMain::getError (__CLASS__,__FUNCTION__,'Loading files from disk is prohibited (check $wgDFRawEnableDisk in DFRawFunctions.php)');
			}
				
			$in['filename'] = str_replace(array('\\','/'), array('',':'), $in['filename']);
			$in['filename'] = cMain::multiexplode(array(";",":"),$in['filename']);
			$error = cRaw::balanceFilenames(&$in['filename']);
			if ($error) return $error;
			
			foreach ($in['filename'] as $filename) {
				if (cRaw::loaded_raw_check ('filename', $filename))
					continue;
				$ID = $wgRawID;
				$wgRaw[$ID] = new cRaw($filename);
				$wgRaw[$ID]->getObject();
			}
		}
		
		
		
		switch ($type)
		{
			//*** Load: filename (displays unformatted raws)
			case 'load':
				if (isset($in['filename']))
					foreach ($wgRaw as $Raw)
						$output .= $Raw->text;
			break;
			
			//*** Key: key
			case 'key':
				if (!isset($in['key'])) return cMain::getError ('Define df:key parameter (key)');
				$output = cMain::keyTrans ($parser, $in);
			break;
			
			//*** Buiding: filename, building, option
			/* case 'building':
				if (!isset($in['filename'], $in['building'], $in['options']))
					return '<span class="error">Define df:building parameters (filename, building, option).</span>';
				$in['option'] = self::multiexplode(array(';',':'),$in['option']);
				$in['building'] = self::multiexplode(array(';',':'),$in['building']);
				$output = self::getBuilding($in);
			break; */
			
			//*** Type: 
			case 'fetch':
				
				if (isset($in['padding'][$i]))
				{
					$in['padding'] = explode('//',$in['padding']);
					//echo 'padding='; print_r($in['padding']);
					for ($i=0; $i<=2; $i++)
					{
						if (!isset($in['padding'][$i]))
						{
							$in['padding'][$i]=array('&'); //##Add variants
							
						}
						$in['padding'][$i] = explode('&',$in['padding'][$i]);
					}
					//echo  'padding='; print_r($in['padding']);
				}
				
					
				if (!isset($in['filename']))
					return cMain::getError ('Define df:type parameter (filename)');
				
				if (isset($in['obj_cond'])) {
					$in['obj_cond'] = str_replace(array(',',';','/'), array(':'), $in['obj_cond']);
					$in['obj_cond'] = cMain::multiexplode(array(':'),$in['obj_cond']);
				}
				
				if (isset($in['tag_cond'])) {
					$in['obj_cond'] = str_replace(array(',',';'), array(';'), $in['obj_cond']);
					$in['tag_cond'] = cMain::multiexplode(array(';',':'),$in['tag_cond']);
				}
				
				// echo '$in='; print_r($in);
				
				foreach ($wgRaw as $raw) {
				    if ($raw->tag === false)
						$error = $raw->getTags();
					if ($error) return $error;
				}
				foreach ($wgRaw as $raw)
					$raw->split_by_object();
				//echo '<br/>'. $wgRaw. ': '; print_r($wgRaw);
			break;
			
			default:
				return cMain::getError ('<span class="error">Df function lacks required functionality. Choose supported type instead of "'. $type .'".</span>');
		}
		
		return (cMain::varSpam());
		//echo '<br/>'. $wgRaw. ': '; print_r($wgRaw[5]);
		
		
		//if ($wgNoWiki>0)
			//return array($output, 'nowiki' => true );
		//return $output;
		
	}
	
	public static function multiexplode ($delimiters,$string) 
	{	
		$tmp = explode($delimiters[0],$string);
		array_shift($delimiters);
		if($delimiters != NULL)
			foreach($tmp as $key => $val)
				$tmp[$key] = self::multiexplode($delimiters, $val);
		return  $tmp;
	}
	
	// Makes "Alt+Ctrl+S" from "CUSTOM_SHIFT_ALT_CTRL_S".
	// $input = array(string: key, replace, join)
	public static function keyTrans ($parser, $input=array())
	{
		if (!isset($input['replace'])) $input['replace']="$1";
		if (!isset($input['join'])) $input['join']='-';
		
		$key = explode("_", $input['key']);
		$tmp = $key[count($key)-1];
		$key[count($key)-1] = 'CUSTOM';
		$invalid = array_diff($key, array("CUSTOM", "ALT", "SHIFT", "CTRL", "NONE"));
		if ($invalid != FALSE){
			$invalid = implode(', ', $invalid);
			return cMain::getError ('Unrecognized keybinding values:'.  $invalid);
		}
		if (in_array("NONE", $key))
			return '';
		if (in_array("SHIFT", $key) === FALSE)
			$tmp = (strtolower($tmp));
		if (in_array("ALT", $key))
			$tmp = "Alt-{$tmp}";
		if (in_array("CTRL", $key))
			$tmp = "Ctrl-{$tmp}";
		$parts = explode('-', $tmp);
		foreach ($parts as &$part) 
			$part = preg_replace('/(.+)/', $input['replace'], $part);
		$tmp = implode($input['join'], $parts);
		return $tmp;
	}
	
	// Takes parameters and encodes them as an unevaluated template transclusion
	// Best used with 'evaluate' below
	public static function delay (&$parser/*, ...*/)
	{
		$args = func_get_args();
		array_shift($args);
		return '{{'. implode('|', $args) .'}}';
	}

	// Evaluates any templates within the specified data - best used with foreachtag
	public static function evaluate (&$parser, $data = '')
	{
		return $parser->replaceVariables($data);
	}
	
	// Error handling
	public static function getError ($error) {
		global $wgError;
		if (!is_string($error)) {
			cMain::getError('Error is not string', E_USER_WARNING);
			break;
		}
		
		//if (isset($this))
			//var_dump($this);
		
		trigger_error($error, E_USER_WARNING);
		$wgError .= '<span class="error">Error</span>';
	}
	
	public static function varSpam () {
		global $wgRaw; 
		$spam = '';
		foreach ($wgRaw as $raw) {
			$spam .= '<br/>';
			$raw = get_object_vars($raw);
			foreach ($raw as $key => $property) {
				if (!$property)
					continue;
				switch ($key) {
				
				case 'text':
					$property = 'lots of text';
				break;
				case 'obj': 
				case 'split_to':
					if (is_array($property))
						$property = implode(':',$property);	
				break;
				case 'ID':
				case 'split_from':
				break;
				default:
					continue (2);
				}
				$spam .= '<br/>'. $key .'='. $property;
			}
		}
		return $spam;
	}
}