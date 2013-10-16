<?php
{
ini_set('display_errors', 'On');
error_reporting(E_ALL);
$wgStringInput = false;
$wgNoWiki = false;
$wgRawPath = array();
$wgErrorPool = array('backtrace' => array(), 'log' => '');
}

class cMain {
	
	public static function dfMain (&$parser, $type = ''/*, ...*/)
	{
		
		global $wgRawPath, $wgRawID, $wgRaw, $wgNoWiki;
		$wgNoWiki = 0;
		$output = '';
				
		// Format additional variables
		for ($i = 2; $i <= func_num_args()-1; $i++)
		{
			$params[$i-2] = preg_replace("|\n|", '', func_get_arg($i));
			$params[$i-2] = explode('=',$params[$i-2],2);
			if (!(count($params[$i-2]) === 2)) {
				return cMain::getError ('Either "=" is missing or too much of them');
			}
			
			//if (in_array($params[$i-2][0],$valid_params))
				$in[$params[$i-2][0]] = $params[$i-2][1];
			//else
				//return '<span class="error">Parameter "'. $params[$i-2][0] .'" is invalid.</span>';
		}
		
		// option
		//if (isset($in['option']))
		//	$in['option'] = explode(',',preg_replace("| |", '', $in['option']));
			
		if (isset($in['nowiki']) and is_numeric($in['nowiki']))
			$wgNoWiki = $in['nowiki'];
		
		// Load file, define object
		if (isset($in['filename']))	{
			
			global $wgDFRawEnableDisk;
			if (!$wgDFRawEnableDisk === true) {
				return cMain::getError ('Loading files from disk is prohibited (check $wgDFRawEnableDisk in DFRawFunctions.php)');
			}
				
			$in['filename'] = str_replace(array('\\','/'), array('',':'), $in['filename']);
			$in['filename'] = cMain::multiexplode(array(";",":"),$in['filename']);
			$error = cRaw::balanceFilenames(&$in['filename']);
			if ($error) return $error;
			
			foreach ($in['filename'] as $filename) {
				$wgRaw[$wgRawID] = new cRaw();
				$loaded = $wgRaw[$wgRawID]->loadRaw($filename);
				if ($loaded !== true) {
					unset($wgRaw[$wgRawID]);
					continue;
				}
				$wgRaw[$wgRawID]->getObject();
				$wgRawID++;
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
				//echo '<br/>'. $wgRaw. ': '; var_dump($wgRaw);
				// echo '$in='; print_r($in);
				foreach ($wgRaw as $Raw) {
					$error = $Raw->getTags();
					if ($error) return $error;
				}
				
				foreach ($wgRaw as $Raw)
					$Raw->split_by_object();
			break;
			
			default:
				return cMain::getError ('<span class="error">Df function lacks required functionality. Choose supported type instead of "'. $type .'".</span>');
		}
		
		//echo '<br/>'. $wgRaw. ': '; var_dump($wgRaw);
		if ($wgNoWiki>0)
			return array($output, 'nowiki' => true );
		return $output;
		
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
		
		if (isset($this))
			$error .= "[ID = {$this->ID}]";
		
		trigger_error($error, E_USER_WARNING);
		if (!is_string($error)) {
			trigger_error('Error is not string', E_USER_WARNING);
			break;
		}
		return '<span class="error">Error</span>';
	}
}