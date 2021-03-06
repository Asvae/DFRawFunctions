﻿<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);
$wgStringInput = false;
$wgNoWiki = false;
$wgRawPath = array();

class DFRawFunctions
{
	
   /**
	*	Takes some raws and returns a 2-dimensional token array
	*   If 2nd parameter is specified, then only tags of the specified type will be returned
	*   Optional 3rd parameter allows specifying an array 
	*   which will be filled with indentation for each line
	*	
	*	Input: {{df_raw:data|type|padding}}
	*	- data: string, raws
	*  	- type: tag is returned only if first subtag = type
	*	- 
	*	
	*		
	*		
	*  	
	*  
	*  
	**/
	private static function getTags ($data, $type = '', &$padding = array())
	{
		
		$raws = array();
		$off = 0;
		$pad = '';
		while (1)
		{
			$start = strpos($data, '[', $off);
			if ($start === FALSE)
				break;
			$end = strpos($data, ']', $start);
			if ($end === FALSE)
				break;
			if ($off < $start)
			{
				$tmp = explode("\n", trim(substr($data, $off, $start - $off), "\r\n"));
				$pad = end($tmp);
			}
			$tag = explode(':', substr($data, $start + 1, $end - $start - 1));
			if (($type == '') || ($tag[0] == $type))
			{
				$padding[] = $pad;
				$raws[] = $tag;
			}
			$off = $end + 1;
		}
		return $raws;
	}
	
   /** 
	*	Input:
	*  	- namespace:filename;namespace:filename;namespace:filename...
	*	- namespace:filename;filename;filename...
	*	- namespace:filename
	*	- some random raws
	*   Checks if specified strings are valid namespace:filename
	*  	If it is, then load and return its contents; otherwise, return input data
	*   Option FIX! fixes masterwork raws, not required if Masterwork namespace is mentioned
    **/
	private static function loadFile ($data, $options='')
	{
		$output=false; $mw=false; $options=explode(":",$options);
		
		// checks if reading from disk is enabled
		global $wgDFRawEnableDisk;
		if (!$wgDFRawEnableDisk)
			if ($output===false){$output=$data;}
		
		// checks if path exists
		global $wgDFRawPath;
		if (!is_dir($wgDFRawPath))
			if ($output===false){$output=$data;}
		
		$filenames = str_replace(array('/', '\\'), '', $data);
		
		$filenames = self::multiexplode(array(";",":"),$filenames);
		if ($filenames[0][0]=="Masterwork"){$mw=true;}
		
		// main module
		foreach ($filenames as $i=>&$filename)
		{	
		
			if ($i=0 and count($filename) != 2){$output=$data; break;}
			if (count($filename)===2){$filename_count=$i;}
			if (count($filename) != 2)
			{
			$filename[1]=$filename[0];
			$filename[0]=$filenames[$filename_count][0];
			}
			
			$wantfile[$i] = $wgDFRawPath .'/'. $filename[0] .'/'. $filename[1];
			if (!is_file($wantfile[$i])){echo ($wantfile[$i]); $output=$data; break;}
			$output.=file_get_contents($wantfile[$i]);
		}
		
		// Masterwork raw fix
		if (($mw === TRUE or in_array("FIX!", $options)) and strpos($output,'!NO')!=FALSE)
			$output=self::masterworkRawFix($output);
		
		return $output;
	}

	/** 
	*	Same as loadFile, but works only with arrays
    **/
	private static function loadRaw ($input)
	{	
		// Check if reading from disk is enabled
		global $wgDFRawEnableDisk;
		if (!$wgDFRawEnableDisk)
			return '<span class="error">Reading from disk is prohibited.</span>';
		
		// Check if path exists
		global $wgDFRawPath;
		if (!is_dir($wgDFRawPath))
			return '<span class="error">Wrong path to file (check $wgDFRawPath in DFRawFunctions.php).</span>';
		
		// Access filename
		global $wgRawPath;
		$filenames=$wgRawPath;
		
		// Balance partial input and load file
		$output='';
		foreach ($filenames as $i=>&$filename)
		{	
		
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
				
			$file=file_get_contents($wantfile[$i]);
			if ($filename[0]='Masterwork')
				$output.=self::masterworkRawFix($file);
			else
				$output.=$file;
		}
		
		return $output;
	}
	
	// Fix corrupted masterwork raws
	public static function masterworkRawFix($string)
	{
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
		return $string;
	}
	
	// Take an entire raw file and extract one entity
	// If 'object' is not specified, returns the entire file
	public static function raw (&$parser, $data = '', $object = '', $id = '', $notfound = '')
	{
		$data = self::loadFile($data);
		if (!$object)
			return ($data);
		$start = strpos($data, '['. $object .':'. $id .']');
		if ($start === FALSE)
			return $notfound;
		$end = strpos($data, '['. $object .':', $start + 1);
		if ($end === FALSE)
			$end = strlen($data);

		// include any plaintext before the beginning
		$tmp = self::rstrpos($data, ']', $start);
		if ($tmp !== FALSE)
			$start = $tmp;
		// and remove any plaintext after the end
		$tmp = self::rstrpos($data, ']', $end);
		if ($tmp !== FALSE)
			$end = $tmp;

		return trim(substr($data, $start, $end - $start));
	}

	// Same as raw(), but allows specifying multiple files and uses the first one it finds
	public static function raw_mult (&$parser, $datas = array(), $object = '', $id = '', $notfound = '')
	{
		foreach ($datas as $data)
		{
			$data = self::loadFile($data);
			$start = strpos($data, '['. $object .':'. $id .']');
			if ($start === FALSE)
				continue;
			$end = strpos($data, '['. $object .':', $start + 1);
			if ($end === FALSE)
				$end = strlen($data);

			// include any plaintext before the beginning
			$tmp = self::rstrpos($data, ']', $start);
			if ($tmp !== FALSE)
				$start = $tmp;
			// and remove any plaintext after the end
			$tmp = self::rstrpos($data, ']', $end);
			if ($tmp !== FALSE)
				$end = $tmp;

			return trim(substr($data, $start, $end - $start));
		}
		return $notfound;
	}

	// Checks if a tag is present, optionally with a particular token at a specific offset
	public static function tag (&$parser, $data = '', $type = '', $offset = 0, $entry = '')
	{
		if ($entry == '')
			$entry = $type;
		$tags = self::getTags($data, $type);
		foreach ($tags as &$tag)
		{
			if ($offset >= count($tag))
				continue;
			if ($tag[$offset] == $entry)
				return TRUE;
		}
		return FALSE;
	}

	// Locates a tag matching certain criteria and returns the tag at the specified offset
	// Num indicates which instance of the tag should be returned - a negative value counts from the end
	// Match condition parameters are formatted CHECKOFFSET:CHECKVALUE
	// If offset is of format MIN:MAX, then all tokens within the range will be returned, colon-separated
	public static function tagentry (&$parser, $data = '', $type = '', $num = 0, $offset = 0, $notfound = 'not found'/*, ...*/)
	{
		$numcaps = func_num_args() - 6;
		$tags = self::getTags($data, $type);
		if (count($tags) == 0)
			return $notfound;
		if ($num < 0)
			$num += count($tags);
		if (($num < 0) || ($num >= count($tags)))
			return $notfound;
		foreach ($tags as &$tag)
		{
			if ($offset >= count($tag))
				continue;
			$match = true;
			for ($i = 0; $i < $numcaps; $i++)
			{
				$parm = func_get_arg($i + 5);
				list($checkoffset, $checkval) = explode(':', $parm);
				if (($checkoffset >= count($tag)) || ($tag[$checkoffset] != $checkval))
				{
					$match = false;
					break;
				}
			}
			if (!$match)
				continue;
			if ($num)
			{
				$num--;
				continue;
			}
			$range = explode(':', $offset);
			if (count($range) == 1)
				return $tag[$offset];
			else
			{
				$out = array();
				for ($i = $range[0]; $i <= $range[1]; $i++)
					$out[] = $tag[$i];
				return implode(':', $out);
			}
		}
		return $notfound;
	}

	// Locates a tag and returns all of its tokens as a colon-separated string
	public static function tagvalue (&$parser, $data = '', $type = '', $num = 0, $notfound = 'not found')
	{
		$tags = self::getTags($data, $type);
		if (count($tags) == 0)
			return $notfound;
		if ($num < 0)
			$num += count($tags);
		if (($num < 0) || ($num >= count($tags)))
			return $notfound;

		$tag = $tags[$num];
		array_shift($tag);
		return implode(':', $tag);
	}

	// Iterates across all matching tags and produces the string for each one, substituting \1, \2, etc. for the tokens
	// Probably won't work with more than 9 parameters
	public static function foreachtag (&$parser, $data = '', $type = '', $string = '')
	{
		$tags = self::getTags($data, $type);
		$out = '';
		foreach ($tags as $tag)
		{
			$rep_in = array();
			for ($i = 0; $i < count($tag); $i++)
				$rep_in[$i] = '\\'. ($i + 1);
			$out .= str_replace($rep_in, $tag, $string);
		}
		return $out;
	}

	// Iterates across all tokens within a specific tag in groups and produces the string for each group, substituting \1, \2, etc.
	// Input data is expected to come from tagvalue()
	public static function foreachtoken (&$parser, $data = '', $offset = 0, $group = 1, $string = '')
	{
		$tag = explode(':', $data);
		$out = '';
		$rep_in = array();
		for ($i = 0; $i < $group; $i++)
			$rep_in[] = '\\'. ($i + 1);
		for ($i = $offset; $i < count($tag); $i += $group)
		{
			$rep_out = array();
			for ($j = 0; $j < $group; $j++)
				$rep_out[] = $tag[$i + $j];
			$out .= str_replace($rep_in, $rep_out, $string);
		}
		return $out;
	}

	// Iterates across all objects in the specified raw file and extracts specific tokens
	// Token extraction parameters are formatted TYPE:OFFSET:CHECKOFFSET:CHECKVALUE
	// If CHECKOFFSET is -1, then CHECKVALUE is ignored; -2 permits the token to be missing altogether
	// If TYPE is "STATE" and OFFSET is "NAME" or "ADJ", then OFFSET and CHECKOFFSET will be fed into statedesc() to return the material's state descriptor
	// Objects which fail to match *any* of the checks will be skipped
	public static function makelist (&$parser, $data = '', $object = '', $string = ''/*, ...*/)
	{
		$data = self::loadFile($data);

		$numcaps = func_num_args() - 4;
		$rep_in = array();
		for ($i = 0; $i < $numcaps; $i++)
			$rep_in[$i] = '\\'. ($i + 1);
		$out = '';
		$off = 0;
		while (1)
		{
			$start = strpos($data, '['. $object .':', $off);
			if ($start === FALSE)
				break;
			$end = strpos($data, '['. $object .':', $start + 1);
			if ($end === FALSE)
				$end = strlen($data);
			$off = $end;
			$tags = self::getTags(substr($data, $start, $end - $start));
			$rep_out = array();
			for ($i = 0; $i < $numcaps; $i++)
			{
				$parm = func_get_arg($i + 4);
				@list($gettype, $getoffset, $checkoffset, $checkval) = explode(':', $parm);
				// permit fetching material state descriptors from here
				if (($gettype == 'STATE') && (in_array($getoffset, array('NAME', 'ADJ'))))
				{
					$val = self::statedesc($parser, substr($data, $start, $end - $start), $getoffset, $checkoffset);
					$rep_out[$i] = $val;
					continue;
				} 
				foreach ($tags as $tag)
				{
					if (($tag[0] != $gettype) || ($getoffset >= count($tag)))
						continue;
					if (($checkoffset < 0) || (($checkoffset < count($tag)) && ($tag[$checkoffset] == $checkval)))  
					{
						$rep_out[$i] = $tag[$getoffset];
						break;
					}
				}
				if (($checkoffset == -2) && !isset($rep_out[$i]))
					$rep_out[$i] = '';
			}
			if (count($rep_in) == count($rep_out))
				$out .= str_replace($rep_in, $rep_out, $string);
		}
		return $out;
	}

	// Determines a material's state descriptor by parsing its raws
	public static function statedesc (&$parser, $data = '', $type = '', $state = '')
	{
		$tags = self::getTags($data);
		$names = array('NAME' => array(), 'ADJ' => array());
		foreach ($tags as $tag)
		{
			if (in_array($tag[0], array('STATE_NAME', 'STATE_NAME_ADJ')))
			{
				if (in_array($tag[1], array('ALL', 'ALL_SOLID', 'SOLID')))
					$names['NAME']['SOLID'] = $tag[2];
				if (in_array($tag[1], array('ALL', 'ALL_SOLID', 'SOLID_POWDER', 'POWDER')))
					$names['NAME']['POWDER'] = $tag[2];
				if (in_array($tag[1], array('ALL', 'ALL_SOLID', 'SOLID_PASTE', 'PASTE')))
					$names['NAME']['PASTE'] = $tag[2];
				if (in_array($tag[1], array('ALL', 'ALL_SOLID', 'SOLID_PRESSED', 'PRESSED')))
					$names['NAME']['PRESSED'] = $tag[2];
				if (in_array($tag[1], array('ALL', 'LIQUID')))
					$names['NAME']['LIQUID'] = $tag[2];
				if (in_array($tag[1], array('ALL', 'GAS')))
					$names['NAME']['GAS'] = $tag[2];
			}
			if (in_array($tag[0], array('STATE_ADJ', 'STATE_NAME_ADJ')))
			{
				if (in_array($tag[1], array('ALL', 'ALL_SOLID', 'SOLID')))
					$names['ADJ']['SOLID'] = $tag[2];
				if (in_array($tag[1], array('ALL', 'ALL_SOLID', 'SOLID_POWDER', 'POWDER')))
					$names['ADJ']['POWDER'] = $tag[2];
				if (in_array($tag[1], array('ALL', 'ALL_SOLID', 'SOLID_PASTE', 'PASTE')))
					$names['ADJ']['PASTE'] = $tag[2];
				if (in_array($tag[1], array('ALL', 'ALL_SOLID', 'SOLID_PRESSED', 'PRESSED')))
					$names['ADJ']['PRESSED'] = $tag[2];
				if (in_array($tag[1], array('ALL', 'LIQUID')))
					$names['ADJ']['LIQUID'] = $tag[2];
				if (in_array($tag[1], array('ALL', 'GAS')))
					$names['ADJ']['GAS'] = $tag[2];
			}
		}
		if (!isset($names[$type]))
			return '';
		if (!isset($names[$type][$state]))
			return '';
		return $names[$type][$state];
	}

	// Internal function used by cvariation, inserts new tags into the list at a particular offset
	private static function cvariation_merge (&$output, &$out_pad, &$insert, &$insert_pad, $insert_offset)
	{
		if ($insert_offset == -1)
		{
			// splice can't actually append to the end of the array
			$output = array_merge($output, $insert);
			$out_pad = array_merge($out_pad, $insert_pad);
		}
		else
		{
			array_splice($output, $insert_offset, 0, $insert);
			array_splice($out_pad, $insert_offset, 0, $insert_pad);
		}
		$insert = array();
		$insert_pad = array();
	}

	// Parses a creature variation to produce composite raws
	public static function cvariation (&$parser, $data = '', $base = ''/*, ...*/)
	{
		$variations = array();
		for ($i = 3; $i < func_num_args(); $i++)
			$variations[] = func_get_arg($i);

		$insert_offset = -1;
		$insert_pad = array();
		$insert = array();

		$var_pad = array();
		$vardata = array();

		$out_pad = array();
		$output = array();

		$in_pad = array();
		$input = self::getTags($data, '', $in_pad);

		// remove object header tag so new tags don't get inserted in front of it
		$start = array_shift($input);
		$start_pad = array_shift($in_pad);

		foreach ($input as $x => $tag)
		{
			$padding = $in_pad[$x];
			switch ($tag[0])
			{
			case 'COPY_TAGS_FROM':
				$base_pad = array();
				$basedata = self::getTags(self::raw($parser, $base, 'CREATURE', $tag[1]), '', $base_pad);
				// discard the object definition
				array_shift($basedata);
				array_shift($base_pad);
				$output = array_merge($output, $basedata);
				$out_pad = array_merge($out_pad, $base_pad);
				break;
			case 'APPLY_CREATURE_VARIATION':
				// if any CV_* tags were entered already, append this to them
				$vardata = array_merge($vardata, self::getTags(self::raw_mult($parser, $variations, 'CREATURE_VARIATION', $tag[1]), '', $var_pad));
			case 'APPLY_CURRENT_CREATURE_VARIATION':
				// parse the creature variation and apply it to the output so far
				foreach ($vardata as $y => $vartag)
				{
					$varpad = $var_pad[$y];
					$cv_tag = array_shift($vartag);
					$varlen = count($vartag);
					switch ($cv_tag)
					{
					case 'CV_NEW_TAG':
					case 'CV_ADD_TAG':
						$insert[] = $vartag;
						$insert_pad[] = $varpad;
						break;
					case 'CV_REMOVE_TAG':
						$adjust = 0;
						foreach ($output as $z => $outtag)
						{
							if (array_slice($outtag, 0, $varlen) == $vartag)
							{
								if ($z < $insert_offset)
									$adjust++;
								unset($output[$z]);
								unset($out_pad[$z]);
							}
						}
						// reset indices
						$output = array_merge($output);
						$out_pad = array_merge($out_pad);
						$insert_offset -= $adjust;
						break;
					case 'CV_CONVERT_TAG':
						$conv = array();
						break;
					case 'CVCT_MASTER':
						foreach ($output as $z => $outtag)
						{
							if ($outtag[0] == $vartag[0])
							{
								$conv[] = $z;
								break;
							}
						}
						break;
					case 'CVCT_TARGET':
						$conv_from = ':'. implode(':', $vartag) .':';
						break;
					case 'CVCT_REPLACEMENT':
						$conv_to = ':'. implode(':', $vartag) .':';
						foreach ($conv as $z)
						{
							$conv_data = str_replace($conv_from, $conv_to, implode(':', $output[$z]) .':');
							$output[$z] = explode(':', trim($conv_data, ':'));
						}
						break;
					}
				}
				self::cvariation_merge($output, $out_pad, $insert, $insert_pad, $insert_offset);
				// then clear the variation buffer
				$var_pad = array();
				$vardata = array();
				// reset to inserting at the end
				$insert_offset = -1;
				break;
			case 'GO_TO_START':
				self::cvariation_merge($output, $out_pad, $insert, $insert_pad, $insert_offset);
				$insert_offset = 0;
				break;
			case 'GO_TO_END':
				self::cvariation_merge($output, $out_pad, $insert, $insert_pad, $insert_offset);
				$insert_offset = -1;
				break;
			case 'GO_TO_TAG':
				self::cvariation_merge($output, $out_pad, $insert, $insert_pad, $insert_offset);
				// if we don't actually find the tag, then insert at the end
				$insert_offset = -1;
				$taglen = count($tag) - 1;
				foreach ($output as $z => $outtag)
				{
					if ($outtag == array_slice($tag, 1, $taglen))
					{
						$insert_offset = $z;
						break;
					}
				}
				break;
			case 'CV_NEW_TAG':
			case 'CV_ADD_TAG':
			case 'CV_REMOVE_TAG':
			case 'CV_CONVERT_TAG':
			case 'CVCT_MASTER':
			case 'CVCT_TARGET':
			case 'CVCT_REPLACEMENT':
				$vardata[] = $tag;
				$var_pad[] = $padding;
				break;
			default:
				$insert[] = $tag;
				$insert_pad[] = $padding;
				break;
			}
		}
		// Merge any remaining tags
		self::cvariation_merge($output, $out_pad, $insert, $insert_pad, $insert_offset);

		// prepend object header tag
		array_unshift($output, $start);
		array_unshift($out_pad, $start_pad);

		foreach ($output as $x => &$data)
			$data = $out_pad[$x] .'['. implode(':', $data) .']';
		return implode("\n", $output);
	}

	// Performs multiple string replacements
	public static function mreplace (&$parser, $data = ''/*, ...*/)
	{
		$numargs = func_num_args() - 2;
		$rep_in = array();
		$rep_out = array();
		for ($i = 0; $i < $numargs; $i += 2)
		{
			$rep_in[] = func_get_arg($i + 2);
			if ($i == $numargs + 2)
				$rep_out[] = '';
			else	$rep_out[] = func_get_arg($i + 3);
		}
		return str_replace($rep_in, $rep_out, $data);
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

	// equivalent of lastIndexOf, search backwards for needle in haystack and return its position
	private static function rstrpos ($haystack, $needle, $offset)
	{
		$size = strlen($haystack);
		$pos = strpos(strrev($haystack), $needle, $size - $offset);
		if ($pos === false)
			return false;
		return $size - $pos;
	}
	
	/* 
	Input is: 1|2|3|4|5|6
	1) Data location:
			Masterwork:reaction_kobold.txt
	2) Object:
			"REACTION"
	3) Requirement: checks if those are present in Object
			"BUILDING:TANNER"
		or	"BUILDING"
	4) Type (inputs the following value if requirements are met):
			"NAME"
	inputs	"craft bone shovel"
	5) Number:
		1.	"-1"		returns the very last input with fulfilled requirements and Type
		2.	""			returns whole list of Types, numbered and comma separated
		3.	"N"			returns reaction number N, no formatting
		4.	"FORMAT" 	returns reaction number N, wiki table formatting and Description
		5.	"CHECK"		checks if Nth Type is the last one, returns error if it's not.
		6.  "ORDER"		compares first tags with Object, otherwise searches through every tag.
	6) Description: description for wiki, works only with "N:FORMAT"
	*/
	
	// {{#df:type|object=BUILDING_WORKSHOP|filename=building_masterwork|obj_req=BUILDING_WORKSHOP:CHANDLER|tag_req=BUILD_LABOR}}
	public static function getType ($in)
	{
	
		if (!isset($in['padding'])) $in['padding'] = '';
		if (!isset($in['option'])) $in['option'] = array_fill(0,count($in['tag_cond']),array());;
		if (!isset($in['obj_cond'])) $in['obj_cond'] = array(array($in['object']));
		$zeros = 		array_fill(0,count($in['obj_cond']),0);
		$ones = 		array_fill(0,count($in['obj_cond']),1);
		$obj_check =	$zeros;
		
		echo $in['object'];
		//$object = '', $requirement = '', $l_type = '', $number = '',  $description = ''
	
		$tag = self::getTags($in['data']);
		
		$e = 0; $i = -1; $return_value = ''; $tmp=array(); $obj_num = -1;
		$that_obj = false;
		
		while (++$i<=(count($tag)-1))
		{	
			//echo '<br/>'.$i; print_r(array_diff($condition, $tag[$i]));
			// Checks if left tag fits object
			if ($tag[$i][0] === $in['object'])
			{ 	
				$that_obj = false; 
				$i_obj = $i; $obj_check = $zeros;
			}	
				
			// Check obj_cond, leap back on affirm
			if ($that_obj === false)
			{
				foreach ($in['obj_cond'] as $c => $condition)
					if  (array_diff($condition, $tag[$i]) === array())
						$obj_check[$c] = 1;
						
				if ($obj_check === $ones)
				{
					$that_obj = true; 
					$i=$i_obj; $obj_num++;
					echo $obj_num;
				}
			}
			else
			{
				foreach ($in['tag_cond'] as $c => $condition)
				{
					if (in_array('order',$in['option'][$c]))
					{
						if (array_intersect_assoc($condition, $tag[$i]) === $condition)
						{
							$tmp[$obj_num][$i] = array_slice($tag[$i], count($condition));
						} 
					}
					else
					{
						$difference=array_diff($tag[$i], $condition);
						if (count($difference) != count($tag[$i]))
							$tmp[$obj_num][$i] = $difference;
					}
				}
			}
			// Weird syntax, just extracts first value. 
			// if ($FirstOnly === TRUE and $e !== 0){
			// $tmp[$e-1]=(explode (':',$tmp[$e-1]));
			// $tmp[$e-1]=$tmp[$e-1][0];}
		}
		
		//if ($Doubles === TRUE)
		//{
			//if	($tmp != array_unique($tmp)){
			//return '<span class="error">Output contains doubles!</span>';}
			//else {return '';}
		//}
		
		//$step='';
		//if (($l_type[0]=="BUILDING" and isset($l_type[1]))or($l_type[0]=="BUILD_KEY")){
			//foreach ($tmp as &$step)
			//$step = self::keyTrans($parser, $step);
			//}
			
		//if ($Check and $Number === ''){return "''' There is ".count(array_unique($tmp))." ".implode(":",$l_type)."s in total.'''";}
		//if ($Number === '') 
			//return implode(", ",array_unique($tmp));
		//if ($Number == -1)
			// return "Last reaction of the TYPE is: '''". ($e-1) .". ". $tmp[$e-1] .".'''";
		// if ($Number != ($e-1) and $Check)
			// return "'''".'<span class="error">Error: Last '.implode(":",$l_type).' is '.($e-1)." and not ". $Number.".</span>'''";
		// if ($Format)
			// return "'''".($Number).". ". $tmp[$Number] ."''' || " .$description;
		//otherwise
		if (!isset($i_obj)) return "<span class=\"error\">No ".$in['object']." is found.</span>";
		if (!$tmp) return "<span class=\"error\">obj_cond is not met.</span>";
		//echo '<br/>tmp='; print_r($tmp);
		$i1=-1;
		foreach ($tmp as &$obj)
		{	
			$i1++;
			$i2=-1;
			foreach ($obj as  &$obj_tag)
			{	
				$i2++;
				$i3=-1;
				foreach ($obj_tag as &$one_tag)
				{
					$i3++;
					echo ('  '. $i3 .'-'. (count($obj_tag)-1) .'  ');
					if ($i3 === count($obj_tag)-1) break;
					$one_tag = preg_replace('/(&)/', $one_tag, $in['padding'][0]);
				}
				$obj_tag = implode($obj_tag);
				if ($i2 === count($obj)-1) break;
				$obj_tag = preg_replace('/(&)/', $obj_tag, $in['padding'][1]);
			}
			$obj = implode($obj);
			if ($i1 === count($tmp)-1) break;
			$obj = preg_replace('/(&)/', $obj, $in['padding'][2]);
			
		}
		unset ($obj, $obj_tag, $one_tag);
		$out = implode($tmp);
		return $out;
	}
	
	
	
	// Makes "Alt+Ctrl+S" from "CUSTOM_SHIFT_ALT_CTRL_S".
	public static function keyTrans ($input=array())
	{
		if (!isset($input['replace'])) $input['replace']="$1";
		if (!isset($input['join'])) $input['join']='-';
		
		$key = explode("_", $input['key']);
		$tmp = $key[count($key)-1];
		$key[count($key)-1] = 'CUSTOM';
		$invalid = array_diff($key, array("CUSTOM", "ALT", "SHIFT", "CTRL", "NONE"));
		if ($invalid != FALSE){
			$invalid = implode(', ', $invalid);
			return "<span class=\"error\">Unrecognized keybinding values: $invalid</span>";
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
	
	
	
	/*###DF_BUILDING### Provides information about workshops and furnaces. 
	building - should be either workshop or furnace with syntax as follows:  "BUILDING_FURNACE:MAGMA_GENERATOR" or "NAME:Magma Generator (Dwarf)".
	options - DIM returns dimensions, TILE:N returns tiles as xHTML table
	
	building - supports multiple entries, they should be separated by ';'
	Example:
	- if 2 parameters: 1st should be BUILDING_FURNACE, BUILDING_WORKSHOP or NAME :INVENTOR;:TAILOR;:Brewery;Inventor's Workbench;Gong
	
	*/
	public static function getBuilding ($input = array())
	{
		// $mtime = microtime(); $mtime = explode(" ",$mtime); $mtime = $mtime[1] + $mtime[0]; $starttime = $mtime; 
		
		// Defining variables and input check
		$tags = array(); $dim = array(); $block = array(); $color = array(); $tile = array(); $item=array(); $single_tag=array();
		$j = 0; $i = 0; $type_check = FALSE;  $single_tag_counter=0; 
		$item_counter=-1;  $bMagma=FALSE; $output_tmp=FALSE;
		$building_invalid=array("BUILDING_FURNACE", "BUILDING_WORKSHOP", "NAME");
		
		$tags = self::getTags(self::loadRaw($input['filename']));
		print_r($input);
		
		// Check building and fix partial input
		foreach ($input['building'] as $i=>&$building)
		{	if (count($building)==2)
				$invalid=array_diff(array($building[0]), $building_invalid);
			if ($invalid)
				return "<span class=\"error\">Unrecognized building type: ".implode(', ',$invalid)."</span>";
				
			if (!isset($building[1]))
			{
				$building[1]=$building[0]; 
				$building[0]="ANY";
			}
		}unset($building);
		
		
		$option_invalid=explode(", ","TILE, COLOR, DIM, 0, 1, 2, 3, WORK_LOCATION, BUILD_ITEM, NOWIKI, TILESET, NAME, BLOCK, BUILDING, NAME_COLOR, BUILD_LABOR, BUILD_KEY, BUILD_ITEM");
		
		// options_limit checks for unneeded options, those will be omitted later
		$options_limit=$option_invalid;
		foreach ($input['option'] as &$option)
		{	
			// patches for specific options
			if (in_array("TILESET",$option)){$option["tile"]="TILE";}
			if (in_array("COLOR",$option)){$option["tile"]="TILE";}
			if (in_array("BLOCK",$option)){$option["work_location"]="WORK_LOCATION";}
			
			$invalid=array_diff($option, $option_invalid);
			if ($invalid)
				return "<span class=\"error\">Unrecognized option: ".implode(', ',$invalid)."</span>";
			$options_limit=array_diff($options_limit, $option);
			
			$option["building_stage"] = preg_grep("/[0-2]/",$option);
			if ($option["building_stage"]==false){$option["building_stage"]=3;
			}else{	
				$options["building_stage"] = $option["building_stage"][0];}
		} unset ($option);
		//echo "option=". implode(', ',array_diff($option_invalid,$options_limit)) ."<br/>";
		// Break limit for NAME
		$options_limit=array_diff($options_limit,array("NAME"));
		$shopsum=array(); $shop=-1;
		//echo "\n\r  buildings="; print_r($input['building']);
		// Extract arrays: dim (workshop dimensions), work_location, block, tile, color, item, single_tag from tag for required buildings
		foreach ($tags as $i=>$tag)
		{	// operates shop number
			if (!array_diff(array($tag[0]), array("BUILDING_FURNACE", "BUILDING_WORKSHOP")))
			{	
				$type_check = false;
				$single_tag_counter=0; 
				$item_counter=-1;
				$building_tag=$tag; // holds building tag in case name is set
			}
			
			// makes type_check true if shop name fits
			if ($type_check === false)
				foreach ($input['building'] as $i=>$building)
					if (isset($tag[1]))
						if ($building[1]==='ANY' or ((($building[0] === "ANY" and in_array($tag[0], array("BUILDING_FURNACE", "BUILDING_WORKSHOP","NAME"))) or ($tag[0] === $building[0])) and $tag[1] === $building[1]))
						{
							$type_check = true; 
							$item_counter=-1;
							$shop++;
						}
			

			// substract every required data into big array - shopsum
			if ($type_check === true and !in_array($tag[0],$options_limit))
			{	
				switch ($tag[0])
				{	
					case "NAME":
						$shopsum["BUILDING"][$shop]=$building_tag;
					case "NAME_COLOR":
					case "BUILD_LABOR":
					case "BUILD_KEY":
						$shopsum[$tag[0]][$shop]=$tag[1];break;
					case "BUILDING_WORKSHOP":
					case "BUILDING_FURNACE":
						break;
					case "DIM":
						$shopsum[$tag[0]][$shop]=array_slice($tag,1,3);break;
					case "WORK_LOCATION":
						$shopsum[$tag[0]][$shop]=array_slice($tag,1);break;
					case "BLOCK":
						$shopsum[$tag[0]][$shop][$tag[1]-1]=array_slice($tag,2);break;
					case "TILE":
						$shopsum[$tag[0]][$tag[1]][$shop][$tag[2]-1]=array_slice($tag,3);break;
					case "COLOR":
						$shopsum[$tag[0]][$tag[1]][$shop][$tag[2]-1]=array_slice($tag,3);break;
					case "NEEDS_MAGMA":
						$shopsum[$tag[0]][$shop]=TRUE;break;
					case "BUILD_ITEM":
						$item_counter++; $single_tag_counter=0;
						$shopsum[$tag[0]][$shop][$item_counter]=array_slice($tag,1);
					break;
					default:
						if (!in_array("BUILD_ITEM",$options_limit))
						{
							$shopsum["SINGLE_TAG"][$shop][$item_counter][$single_tag_counter] = $tag;
							$single_tag_counter++;
						}
					break;
				}
				//echo implode(':',$tag) ."\n\r";
			}
		}
		//echo "\n\r  building_stage=".$option["building_stage"];
		//echo "\n\r  array dimensions=".count($shopsum).":".count($options);
		//echo "\n\r  tile_3="; print_r($shopsum['TILE'][3]);
		//echo "\n\r  color_3="; print_r($shopsum['COLOR'][3]);
		print_r($input['option']);
		foreach ($input['option'] as $i => $option)
		{	
			print_r($option);
			//echo implode($option);
			for ($j=0; $j<=count($shopsum["BUILDING"])-1; $j++)
			{	//DIM
				if (in_array("DIM",$option))
					{$output_tmp[$i][$j]=implode("&#x2715;",$shopsum["DIM"][$i]);}
				
			}
			
			if (in_array("TILESET",$option))
			{	// TILESET
				$output_tmp[$i]=self::colorTile($parser, $shopsum['TILE'][$option["building_stage"]],'', "[[File:Phoebus 16x16.png|link=]]", 16);}
			else
			{	// TILE:COLOR
				if (in_array('TILE',$option) and in_array("COLOR",$option))
				$output_tmp[$i]=self::colorTile($parser, $shopsum['TILE'][$option["building_stage"]], $shopsum["COLOR"][$option["building_stage"]]);
				// TILE
				if (in_array('TILE',$option) and !in_array("COLOR",$option))
				$output_tmp[$i]=self::colorTile($parser, $shopsum['TILE'][$option["building_stage"]], '');
				// COLOR
				if (!in_array('TILE',$option) and in_array("COLOR",$option))
				$output_tmp[$i]=self::colorTile($parser, '', $shopsum['COLOR'][$option["building_stage"]]);
			}
			// BLOCK
			if (in_array("BLOCK",$option))
			{	
				$block_tile=array(); $block_color=array();
				for ($shop = 0; $shop<=(count($shopsum['BLOCK'])-1); $shop++)
				{
					$block_work_location=$shopsum["WORK_LOCATION"][$shop];
					$dim_1=count($shopsum['BLOCK'][$shop]);
					$dim_0=count($shopsum['BLOCK'][$shop][0]);
					for ($i_ = 0; $i_ <= ($dim_1-1); $i_++)
					{
						$block_color[$shop][$i_]=array();
						
						for ($j_ = 0; $j_ <= ($dim_0-1); $j_++)
						{	$block_tile[$shop][$i_][]='114';
							if ($block_work_location[0]-1===$j_ and $block_work_location[1]-1===$i_)
							{
								array_push($block_color[$shop][$i_],'3','7','0');
							}
							elseif ($shopsum['BLOCK'][$shop][$i_][$j_]===0)
							{
								array_push($block_color[$shop][$i_],'7','0','0');
							}else{
								array_push($block_color[$shop][$i_],'7','3','1');
							}
						}
					}
					
				}
				$output_tmp[$i]=self::colorTile($parser, $block_tile, $block_color);
			}
			// !!!BUILD_ITEM
			if (in_array("BUILD_ITEM",$option))
			{
				$output_tmp[$i]=self::getItem($parser, $shopsum["BUILD_ITEM"], $shopsum["SINGLE_TAG"]);
			}
			// !!!REACTION
			// !!!BUILD_PROFESSION
			//
		}
			// Diagonally mirroring array
			foreach ($output_tmp as $i => $option_output_tmp)
				foreach ($option_output_tmp as $j => $building_option_output_tmp)
					$output[$j][$i] = $building_option_output_tmp;
					
			echo "output=";print_r($output);
			foreach ($output as $i => &$building_input)
				$building_input = '<tr align="center"><td>'. implode('</td><td>', $building_input) .'</td></tr>';
				
		return '<table border="1" cellspacing="0" cellpadding="6">'. implode($output) .'</table>';
		
				
		### Return items
		// if (in_array("BUILD_ITEM",$options) and $item[$j]!='')
		// {	
			// $tmp='';
			// for ($j = 0; $j <= (count($item)-2); $j++) // Turn array into string.
			// $tmp .= implode(":",$item[$j])."<br/>";
			// $tmp .= implode(":",$item[count($item)-1]);
			// $item=$tmp;
			
			// $tmp='';
			// for ($j = 0; $j <= (count($single_tag)-2); $j++) // Turn array into string.
			// if ($single_tag[$j]){
			// $tmp .= implode(":",$single_tag[$j])."<br/>";}
			// else {$tmp .='<br/>';}
			// $tmp .= implode(":",$single_tag[count($single_tag)-1]);
			// $single_tag=$tmp;
			
			// if ($output===FALSE){$output=self::getItem($parser, $item, $single_tag, "BUILD_ITEM");};
		// }
		
		// if (in_array("BUILD_ITEM",$options) and $item[$j]=='')
		// $output = 'none';
		
		// if (in_array("NOWIKI",$options))
		// return array( $output, 'nowiki' => true );
		
		// return $output;
		
		//$mtime = microtime();$mtime = explode(" ",$mtime);$mtime = $mtime[1] + $mtime[0]; $endtime = $mtime; $totaltime = ($endtime - $starttime); echo "This page was created in ".$totaltime." seconds"; 
	}
	
	
	
	/* ###### Returns item parameters based on whatever.
	{{#df_item:item|single_tag|options}}
	* item:
		use ';' to separate items, ':' for tags
	* separate_tag:
		use ';' to separate items, ',' for tag groups, ':' for tags if required
	* options
		only BUILD_ITEM is supported ATM
	
	
		*/
	public static function getItem (&$parser, $item = '', $single_tag='', $options = '')
	{	
		global $wgStringInput; $wgNoWiki;
		// Simple items don't have personal files (taken from dwarffortresswiki.org/index.php/DF2012:Item_token)
		$simple_item=array('BAR','SMALLGEM'=>'cut gem','BLOCKS','ROUGH'=>'rough gem','BOULDER','WOOD'=>	'log','DOOR','FLOODGATE','BED','CHAIR','CHAIN','FLASK','GOBLET','WINDOW','CAGE','BARREL','BUCKET','ANIMALTRAP'=>'animal trap','TABLE','COFFIN','STATUE','CORPSE','BOX','BIN','ARMORSTAND','WEAPONRACK'=>'weapon rack','CABINET','FIGURINE','AMULET','SCEPTER','CROWN','RING','EARRING','BRACELET','GEM','ANVIL','CORPSEPIECE'=>'body part','REMAINS'=>'vermin remains','MEAT','FISH','FISH_RAW'=>'raw fish','VERMIN'=>'live vermin','PET'=>'tame vermin','SEEDS','PLANT','SKIN_TANNED'=>'leather','LEAVES','THREAD','CLOTH','TOTEM','PANTS','BACKPACK','QUIVER','CATAPULTPARTS'=>'catapult part','BALLISTAPARTS'=>'ballista parts','SIEGEAMMO'=>'siege ammo','BALLISTAARROWHEAD'=>'ballista arrow head','TRAPPARTS'=>'mechanism','DRINK','POWDER_MISC'=>'powder','CHEESE','LIQUID_MISC'=>'liquid','COIN','GLOB'=>'tallow','ROCK'=>'small rock','PIPE_SECTION'=>'pipe section','HATCH_COVER'=>'hatch cover','GRATE','QUERN','MILLSTONE','SPLINT','CRUTCH','TRACTION_BENCH'=>'traction bench','ORTHOPEDIC_CAST'=>'orthopedic cast','SLAB','EGG','BOOK');
		echo '=='.$wgStringInput.'==';
		// string input support
		if (is_string($item))
			$wgStringInput = true;
		echo '=='.$wgStringInput.'==';
		if ($wgStringInput)
		{
			$input['ITEM'][0]=self::multiexplode(array(';',':'),$item);
			$input['SINGLE_TAG'][0]=self::multiexplode(array(';',',',':'),$single_tag);
			$options=explode(':',$options);
		}
		else
		{
		$input['ITEM']=$item; $input['SINGLE_TAG']=$single_tag;
		}
		
		//## Initial input check.
		//echo "\n Items|single tags="; print_r($input);
		//echo "\n Options="; print_r($options);
		
		$output=array();
		$input['SINGLE_TAG']=self::getToken($input['SINGLE_TAG']);
		foreach ($input['ITEM'] as $shop=>$input_shop)
		{
			foreach ($input_shop as $line=>$input_line)
			{	
				$tmp='';
				if ($input['SINGLE_TAG'][$shop][$line]!=='')
					$tmp=$input['SINGLE_TAG'][$shop][$line].' ';
				$tmp.=$input_line[0].' ';
				$branch_params=explode(', ','TOOL, FOOD, TRAPCOMP, SIEGEAMMO, PANTS,	AMMO, GLOVES, HELM, SHIELD, SHOES, ARMOR, WEAPON, TOY, INSTRUMENT');
				if (in_array($input_line[1],$branch_params))
					$branch[$shop][$line]=$input_line;
					
				if (in_array($input_line[1],array_keys($simple_item)))
				{	
					if (isset($simple_item[$input_line[1]]))
					{
						$tmp.=$simple_item[$input_line[1]];
					}
					else
						$tmp.=strtolower($input_line[1]);
				} 
				else
					if ($tmp!='')
					{
						$tmp.='<span class="error">Unidentified item '.$input_line[1].'. </span>';
					}
					//case "TOOL":		
					//$tmp.=self::getType($parser, "Masterwork:item_tool_masterwork.txt", "ITEM_TOOL","ITEM_TOOL".":".$input_line[2], "NAME", "FIRST_ONLY");  break;
					//case "TOY":	
					//$tmp.=self::getType($parser, "Masterwork:item_toy_Masterwork.txt", "ITEM_TOY","ITEM_TOY".":".$input_line[2], "NAME", "FIRST_ONLY").self::getType($parser, "Masterwork:item_tool.txt", "ITEM_TOY","ITEM_TOY".":".$item[$i][2], "NAME", "FIRST_ONLY");
				
				$output[$shop][$line]=$tmp;
			}
			if (in_array("FORMAT",$options))
				$output[$shop]='<ul><li>'. implode('</li><li>',$output[$shop]) .'</li></ul>';
			if (!in_array("FORMAT",$options))
				$output[$shop]=implode('<br/>',$output[$shop]);
		}
		print_r($output);
		/* Non simple items
	INSTRUMENT	item_instrument.txt
	TOY	item_toy.txt
	WEAPON	item_weapon.txt
	ARMOR	item_armor.txt
	SHOES	item_shoes.txt
	SHIELD	item_shield.txt
	HELM	item_helm.txt
	GLOVES	item_gloves.txt
	AMMO	item_ammo.txt
	TRAPCOMP	item_trapcomp.txt
	*/
		
		//String input check
		if ($wgStringInput)
			$output=$output[0];
			
		//Nowiki check
		if (in_array("NOWIKI",$options))
			return array( $output, 'nowiki' => true );
			
		return $output;
	}
	
	
	
	// returns html values of tile and color taken meaningful strings from raws (without building stage and line)
	// meant to be used in par with
	public static function colorTile (&$parser, $tile='', $color='', $image='', $step='', $options='')
	{	
		$wgStringInput=false; 
		if (is_string($tile) and is_string($color))
			$wgStringInput=true;
		if($wgStringInput)
			if (($image !=='' and $step==='')or($image ==='' and $step!=='')){return '<span class="error">Either image or step are missing!</span>';}
			
		$options=explode(':',$options);
		
		// TILE
		if ($tile!=='')
		{	$conv_unicode=explode(" "," &#x263A; &#x263B; &#x2665; &#x2666; &#x2663; &#x2660; &#x2022; &#x25D8; &#x25CB; &#x25D9; &#x2642; &#x2640; &#x266A; &#x266B; &#x263C; &#x25BA; &#x25C4; &#x2195; &#x203C; &#x00B6; &#x00A7; &#x25AC; &#x21A8; &#x2191; &#x2193; &#x2192; &#x2190; &#x221F; &#x2194; &#x25B2; &#x25BC; &nbsp; &#x0021; &#x0022; &#x0023; &#x0024; &#x0025; &#x0026; &#x0027; &#x0028; &#x0029; &#x002A; &#x002B; &#x002C; &#x002D; &#x002E; &#x002F; &#x0030; &#x0031; &#x0032; &#x0033; &#x0034; &#x0035; &#x0036; &#x0037; &#x0038; &#x0039; &#x003A; &#x003B; &#x003C; &#x003D; &#x003E; &#x003F; &#x0040; &#x0041; &#x0042; &#x0043; &#x0044; &#x0045; &#x0046; &#x0047; &#x0048; &#x0049; &#x004A; &#x004B; &#x004C; &#x004D; &#x004E; &#x004F; &#x0050; &#x0051; &#x0052; &#x0053; &#x0054; &#x0055; &#x0056; &#x0057; &#x0058; &#x0059; &#x005A; &#x005B; &#x005C; &#x005D; &#x005E; &#x005F; &#x0060; &#x0061; &#x0062; &#x0063; &#x0064; &#x0065; &#x0066; &#x0067; &#x0068; &#x0069; &#x006A; &#x006B; &#x006C; &#x006D; &#x006E; &#x006F; &#x0070; &#x0071; &#x0072; &#x0073; &#x0074; &#x0075; &#x0076; &#x0077; &#x0078; &#x0079; &#x007A; &#x007B; &#x007C; &#x007D; &#x007E; &#x2302; &#x00C7; &#x00FC; &#x00E9; &#x00E2; &#x00E4; &#x00E0; &#x00E5; &#x00E7; &#x00EA; &#x00EB; &#x00E8; &#x00EF; &#x00EE; &#x00EC; &#x00C4; &#x00C5; &#x00C9; &#x00E6; &#x00C6; &#x00F4; &#x00F6; &#x00F2; &#x00FB; &#x00F9; &#x00FF; &#x00D6; &#x00DC; &#x00A2; &#x00A3; &#x00A5; &#x20A7; &#x0192; &#x00E1; &#x00ED; &#x00F3; &#x00FA; &#x00F1; &#x00D1; &#x00AA; &#x00BA; &#x00BF; &#x2310; &#x00AC; &#x00BD; &#x00BC; &#x00A1; &#x00AB; &#x00BB; &#x2591; &#x2592; &#x2593; &#x2502; &#x2524; &#x2561; &#x2562; &#x2556; &#x2555; &#x2563; &#x2551; &#x2557; &#x255D; &#x255C; &#x255B; &#x2510; &#x2514; &#x2534; &#x252C; &#x251C; &#x2500; &#x253C; &#x255E; &#x255F; &#x255A; &#x2554; &#x2569; &#x2566; &#x2560; &#x2550; &#x256C; &#x2567; &#x2568; &#x2564; &#x2565; &#x2559; &#x2558; &#x2552; &#x2553; &#x256B; &#x256A; &#x2518; &#x250C; &#x2588; &#x2584; &#x258C; &#x2590; &#x2580; &#x03B1; &#x00DF; &#x0393; &#x03C0; &#x03A3; &#x03C3; &#x00B5; &#x03C4; &#x03A6; &#x0398; &#x03A9; &#x03B4; &#x221E; &#x03C6; &#x03B5; &#x2229; &#x2261; &#x00B1; &#x2265; &#x2264; &#x2320; &#x2321; &#x00F7; &#x2248; &#x00B0; &#x2219; &#x00B7; &#x221A; &#x207F; &#x00B2; &#x25A0;");
			
			// string input support
			if ($tile!=='' and $wgStringInput)
			{	
				$tile_tmp=self::multiexplode(array(';',':'),$tile);
				unset($tile);
				$tile[0]=$tile_tmp;
			}
			$shops=count($tile);
		}
		
		// COLOR
		if ($color!=='')
		{	$conv_color_foregr=array("0:0" => "#000000", "1:0" => "#000080", "2:0" => "#008000", "3:0" => "#008080", "4:0" => "#800000", "5:0" => "#800080", "6:0" => "#808000", "7:0" => "#C0C0C0", "0:1" => "#808080", "1:1" => "#0000FF", "2:1" => "#00FF00", "3:1" => "#00FFFF", "4:1" => "#FF0000", "5:1" => "#FF00FF", "6:1" => "#FFFF00", "7:1" => "#FFFFFF", "0:0:0" => "#000000", "1:0:0" => "#000080", "2:0:0" => "#008000", "3:0:0" => "#008080", "4:0:0" => "#800000", "5:0:0" => "#800080", "6:0:0" => "#808000", "7:0:0" => "#C0C0C0", "0:0:1" => "#808080", "1:0:1" => "#0000FF", "2:0:1" => "#00FF00", "3:0:1" => "#00FFFF", "4:0:1" => "#FF0000", "5:0:1" => "#FF00FF", "6:0:1" => "#FFFF00", "7:0:1" => "#FFFFFF"," 0:1:0" => "#000000", "1:1:0" => "#000080", "2:1:0" => "#008000", "3:1:0" => "#008080", "4:1:0" => "#800000", "5:1:0" => "#800080", "6:1:0" => "#808000", "7:1:0" => "#C0C0C0", "0:1:1" => "#808080", "1:1:1" => "#0000FF", "2:1:1" => "#00FF00", "3:1:1" => "#00FFFF", "4:1:1" => "#FF0000", "5:1:1" => "#FF00FF", "6:1:1" => "#FFFF00", "7:1:1" => "#FFFFFF"," 0:2:0" => "#000000", "1:2:0" => "#000080", "2:2:0" => "#008000", "3:2:0" => "#008080", "4:2:0" => "#800000", "5:2:0" => "#800080", "6:2:0" => "#808000", "7:2:0" => "#C0C0C0", "0:2:1" => "#808080", "1:2:1" => "#0000FF", "2:2:1" => "#00FF00", "3:2:1" => "#00FFFF", "4:2:1" => "#FF0000", "5:2:1" => "#FF00FF", "6:2:1" => "#FFFF00", "7:2:1" => "#FFFFFF"," 0:3:0" => "#000000", "1:3:0" => "#000080", "2:3:0" => "#008000", "3:3:0" => "#008080", "4:3:0" => "#800000", "5:3:0" => "#800080", "6:3:0" => "#808000", "7:3:0" => "#C0C0C0", "0:3:1" => "#808080", "1:3:1" => "#0000FF", "2:3:1" => "#00FF00", "3:3:1" => "#00FFFF", "4:3:1" => "#FF0000", "5:3:1" => "#FF00FF", "6:3:1" => "#FFFF00", "7:3:1" => "#FFFFFF"," 0:4:0" => "#000000", "1:4:0" => "#000080", "2:4:0" => "#008000", "3:4:0" => "#008080", "4:4:0" => "#800000", "5:4:0" => "#800080", "6:4:0" => "#808000", "7:4:0" => "#C0C0C0", "0:4:1" => "#808080", "1:4:1" => "#0000FF", "2:4:1" => "#00FF00", "3:4:1" => "#00FFFF", "4:4:1" => "#FF0000", "5:4:1" => "#FF00FF", "6:4:1" => "#FFFF00", "7:4:1" => "#FFFFFF"," 0:5:0" => "#000000", "1:5:0" => "#000080", "2:5:0" => "#008000", "3:5:0" => "#008080", "4:5:0" => "#800000", "5:5:0" => "#800080", "6:5:0" => "#808000", "7:5:0" => "#C0C0C0", "0:5:1" => "#808080", "1:5:1" => "#0000FF", "2:5:1" => "#00FF00", "3:5:1" => "#00FFFF", "4:5:1" => "#FF0000", "5:5:1" => "#FF00FF", "6:5:1" => "#FFFF00", "7:5:1" => "#FFFFFF"," 0:6:0" => "#000000", "1:6:0" => "#000080", "2:6:0" => "#008000", "3:6:0" => "#008080", "4:6:0" => "#800000", "5:6:0" => "#800080", "6:6:0" => "#808000", "7:6:0" => "#C0C0C0", "0:6:1" => "#808080", "1:6:1" => "#0000FF", "2:6:1" => "#00FF00", "3:6:1" => "#00FFFF", "4:6:1" => "#FF0000", "5:6:1" => "#FF00FF", "6:6:1" => "#FFFF00", "7:6:1" => "#FFFFFF", "0:7:0" => "#000000", "1:7:0" => "#000080", "2:7:0" => "#008000", "3:7:0" => "#008080", "4:7:0" => "#800000", "5:7:0" => "#800080", "6:7:0" => "#808000", "7:7:0" => "#C0C0C0", "0:7:1" => "#808080", "1:7:1" => "#0000FF", "2:7:1" => "#00FF00", "3:7:1" => "#00FFFF", "4:7:1" => "#FF0000", "5:7:1" => "#FF00FF", "6:7:1" => "#FFFF00", "7:7:1" => "#FFFFFF");
		$conv_color_backgr=array("0:0" => "#000000", "1:0" => "#000000", "2:0" => "#000000", "3:0" => "#000000", "4:0" => "#000000", "5:0" => "#000000", "6:0" => "#000000", "7:0" => "#000000", "0:1" => "#000000", "1:1" => "#000000", "2:1" => "#000000", "3:1" => "#000000", "4:1" => "#000000", "5:1" => "#000000", "6:1" => "#000000", "7:1" => "#000000", "0:0:0" => "#000000", "1:0:0" => "#000000", "2:0:0" => "#000000", "3:0:0" => "#000000", "4:0:0" => "#000000", "5:0:0" => "#000000", "6:0:0" => "#000000", "7:0:0" => "#000000", "0:0:1" => "#000000", "1:0:1" => "#000000", "2:0:1" => "#000000", "3:0:1" => "#000000", "4:0:1" => "#000000", "5:0:1" => "#000000", "6:0:1" => "#000000", "7:0:1" => "#000000", "0:1:0" => "#000080", "1:1:0" => "#000080", "2:1:0" => "#000080", "3:1:0" => "#000080", "4:1:0" => "#000080", "5:1:0" => "#000080", "6:1:0" => "#000080", "7:1:0" => "#000080", "0:1:1" => "#000080", "1:1:1" => "#000080", "2:1:1" => "#000080", "3:1:1" => "#000080", "4:1:1" => "#000080", "5:1:1" => "#000080", "6:1:1" => "#000080", "7:1:1" => "#000080", "0:2:0" => "#008000", "1:2:0" => "#008000", "2:2:0" => "#008000", "3:2:0" => "#008000", "4:2:0" => "#008000", "5:2:0" => "#008000", "6:2:0" => "#008000", "7:2:0" => "#008000", "0:2:1" => "#008000", "1:2:1" => "#008000", "2:2:1" => "#008000", "3:2:1" => "#008000", "4:2:1" => "#008000", "5:2:1" => "#008000", "6:2:1" => "#008000", "7:2:1" => "#008000", "0:3:0" => "#008080", "1:3:0" => "#008080", "2:3:0" => "#008080", "3:3:0" => "#008080", "4:3:0" => "#008080", "5:3:0" => "#008080", "6:3:0" => "#008080", "7:3:0" => "#008080", "0:3:1" => "#008080", "1:3:1" => "#008080", "2:3:1" => "#008080", "3:3:1" => "#008080", "4:3:1" => "#008080", "5:3:1" => "#008080", "6:3:1" => "#008080", "7:3:1" => "#008080", "0:4:0" => "#800000", "1:4:0" => "#800000", "2:4:0" => "#800000", "3:4:0" => "#800000", "4:4:0" => "#800000", "5:4:0" => "#800000", "6:4:0" => "#800000", "7:4:0" => "#800000", "0:4:1" => "#800000", "1:4:1" => "#800000", "2:4:1" => "#800000", "3:4:1" => "#800000", "4:4:1" => "#800000", "5:4:1" => "#800000", "6:4:1" => "#800000", "7:4:1" => "#800000", "0:5:0" => "#800080", "1:5:0" => "#800080", "2:5:0" => "#800080", "3:5:0" => "#800080", "4:5:0" => "#800080", "5:5:0" => "#800080", "6:5:0" => "#800080", "7:5:0" => "#800080", "0:5:1" => "#800080", "1:5:1" => "#800080", "2:5:1" => "#800080", "3:5:1" => "#800080", "4:5:1" => "#800080", "5:5:1" => "#800080", "6:5:1" => "#800080", "7:5:1" => "#800080", "0:6:0" => "#808000", "1:6:0" => "#808000", "2:6:0" => "#808000", "3:6:0" => "#808000", "4:6:0" => "#808000", "5:6:0" => "#808000", "6:6:0" => "#808000", "7:6:0" => "#808000", "0:6:1" => "#808000", "1:6:1" => "#808000", "2:6:1" => "#808000", "3:6:1" => "#808000", "4:6:1" => "#808000", "5:6:1" => "#808000", "6:6:1" => "#808000", "7:6:1" => "#808000", "0:7:0" => "#C0C0C0", "1:7:0" => "#C0C0C0", "2:7:0" => "#C0C0C0", "3:7:0" => "#C0C0C0", "4:7:0" => "#C0C0C0", "5:7:0" => "#C0C0C0", "6:7:0" => "#C0C0C0", "7:7:0" => "#C0C0C0", "0:7:1" => "#C0C0C0", "1:7:1" => "#C0C0C0", "2:7:1" => "#C0C0C0", "3:7:1" => "#C0C0C0", "4:7:1" => "#C0C0C0", "5:7:1" => "#C0C0C0", "6:7:1" => "#C0C0C0", "7:7:1" => "#C0C0C0");
			
			// string input support
			if ($color!=='' and $wgStringInput)
			{	// single color support
				if (strlen($color)<=6 and $tile==='')
				{	switch ($color[0])
					{	case "B":
							$color = $conv_color_backgr[substr($color,1)];
						break;
						case "F":
							$color = $conv_color_foregr[substr($color,1)];
						break;
						default:
						$color = $conv_color_foregr[$color];
						break;
					}
					return  substr($color,1);	
				}
				$color_tmp=explode(";",$color);
				unset($color);
				foreach ($color_tmp as &$color_row)
					$color_row=explode(":",$color_row);
				$color[0]=$color_tmp;
			}
		
			// make "X:Y:Z" strings from array
			$tmp=array();
			foreach ($color as &$shopcolor)
				foreach ($shopcolor as &$color_row)
				{	for ($j=0;$j<=count($color_row)/3-1;$j++)
						$tmp[]=implode(":",array_slice ($color_row,$j*3,3));
					$color_row=$tmp;
					$tmp=array();
				}
			
		}
		
		// Array to HTML conversion
		for ($shop=0; $shop<=count($tile)-1; $shop++)
		{	
			$dim_1=count($tile[$shop]); $dim_0=count($tile[$shop][0]);
			$tile_color[$shop]='<table /border=0 cellpadding=0 cellspacing=0 style="'."font-size:150%; font-family: 'Courier New', monospace; font-weight:bold".'"><tr>';
			if ($color!=='' and $tile!=='' and count($color[$shop])!=count($tile[$shop]))
				return '<span class="error">Dimension mismatch for color and tile in colorTile ('.count($color[$shop]).' vs '.count($tile[$shop]).').</span>';
		
			if ($image)
			{	
				// disable color for tiles
				for ($i = 0; $i <= ($dim_1-1); $i++)
				{	
					$dim_0=count($color[$shop][$i]);
					$tile_color[$shop] .='<tr>';
					for ($j = 0; $j <= ($dim_0-1); $j++)
					{	
						$tile_value=$tile[$shop][$i][$j];
						$x=$tile_value%16; $y=intval($tile_value/16);
						$tile_color[$shop] .= '<td><div style="width:16px;height:16px;overflow:hidden;position:relative"><div style="position:relative;top:-'. $y*$step .'px;left:-' .$x*$step. 'px">'.$image.'</div></div>'.'</td>';
						if ($j==$dim_0-1){$tile_color[$shop].='</tr>';}
					}
					
					if ($i===$dim_1-1 and $j===$dim_0-1)
						$tile_color[$shop] .='</table>';
				}
			}
			else
			{
				if ($color === '')
					for ($i = 0; $i <= ($dim_1-1); $i++)
					{	for ($j = 0; $j <= ($dim_0-1); $j++)
						{	$tile_tmp=$conv_unicode[$tile[$shop][$i][$j]];
							$tile_color[$shop] .= '<td>'.$tile_tmp.'</td>';
							if ($j==$dim_0-1){$tile_color[$shop] .='</tr>';}
						}
						if (($i!=$dim_1-1) and ($j!=$dim_0-1)){$tile_color[$shop] .='<tr>';
						} else {$tile_color[$shop] .='</table>';}
					}
				if ($tile!='' and $color!='')
					for ($i = 0; $i <= ($dim_1-1); $i++)
					{	for ($j = 0; $j <= ($dim_0-1); $j++)
						{	$tile_tmp=$conv_unicode[$tile[$shop][$i][$j]];
							$color_backgr_tmp=$conv_color_backgr[$color[$shop][$i][$j]];
							$color_foregr_tmp=$conv_color_foregr[$color[$shop][$i][$j]];
							$tile_color[$shop] .= '<td><span style="color: '. $color_foregr_tmp .'; background:'. $color_backgr_tmp.'">'.$tile_tmp.'</span></td>';
							if ($j==$dim_0-1){$tile_color[$shop] .='</tr>';}
						}
						if (($i!=$dim_1-1) and ($j!=$dim_0-1)){$tile_color[$shop] .='<tr>';}
						else {$tile_color[$shop] .='</table>';}	
					}
			}
		}
		
		if($wgStringInput)
			$tile_color=$tile_color[0];
		
		if (in_array("NOWIKI",$options))
			return array( $tile_color, 'nowiki' => true );
		return $tile_color;
	}
	
	// delimiters has to be an Array
	// string has to be a String
	public static function multiexplode ($delimiters,$string) 
	{	$tmp = explode($delimiters[0],$string);
		array_shift($delimiters);
		if($delimiters != NULL)
			foreach($tmp as $key => $val)
				$tmp[$key] = self::multiexplode($delimiters, $val);
		return  $tmp;
	}
	
	// Fetches tokens, returns their descriptions (taken from wiki)
	public static function getToken($input)
	{
		// string support
		$wgStringInput = false;
		if (is_string($input))
			$wgStringInput = true;
		if ($wgStringInput)
		{
		$tmp=self::multiexplode(array(';',':',','),$input);
		unset($input);
		$input[0]=$tmp;
		}
		
		// input -> shop -> row -> token group
		foreach ($input as $shop=>$shopinput)
			foreach ($shopinput as $i=>$row)
			{
				$tmp=''; $description=false;
				foreach ($row as $j=>$token)
				{
					switch ($token[0])
					{
						case "REACTION_CLASS":
							$description="Reagent material must have ".$token[1]. "  reaction class.";
						break;
						case "HAS_MATERIAL_REACTION_PRODUCT":
							$description="Reagent material must have ".$token[1]. " material reaction product.";
						break;
						case "CONTAINS":
							$description="Reagent is a container that holds the ".$token[1]. ".";
						break;
						case "UNROTTEN":
							$description="Reagent must not be rotten, mainly for organic materials.";
						break;
						case "CONTAINS_LYE":
							$description="Reagent must be a BARREL or TOOL which contains at least one item of type LIQUID_MISC made of LYE. Use of this token is discouraged, as it does not work with buckets (instead, use [CONTAINS:lye] ? note the colon ? and a corresponding lye reagent [REAGENT:lye:150:LIQUID_MISC:NONE:LYE]).";
						break;
						case "POTASHABLE":
							$description="Alias for [CONTAINS_LYE].";
						break;
						case "NOT_WEB":
							$description="Reagent must be collected (to distinguish silk thread from webs). Only makes sense for items of type THREAD.";
						break;
						case "WEB_ONLY":
							$description="Reagent must be undisturbed (to distinguish silk thread from webs). Only makes sense for items of type THREAD.";
						break;
						case "EMPTY":
							$description="If the reagent is a container, it must be empty.";
						break;
						case "NOT_CONTAIN_BARREL_ITEM":
							$description="If the reagent is a container, it must not contain lye or milk. Not necessary if specifying [EMPTY].";
						break;
						case "BAG":
							$description="Reagent must be a bag - that is, a BOX made of plant fiber, silk, yarn, or leather.";
						break;
						case "GLASS_MATERIAL":
							$description="Reagent material must have the [IS_GLASS] token. All 3 types of glass have this token hardcoded.";
						break;
						case "BUILDMAT":
							$description="Reagent must be a general building material - BAR, BLOCKS, BOULDER, or WOOD.";
						break;
						case "FIRE_BUILD_SAFE":
							$description="Reagent material must be stable at temperatures approaching 11000. Only works with items of type BAR, BLOCKS, BOULDER, WOOD, and ANVIL - all others are considered unsafe.";
						break;
						case "MAGMA_BUILD_SAFE":
							$description="Reagent material must be stable at temperatures approaching 12000. Only works with items of type BAR, BLOCKS, BOULDER, WOOD, and ANVIL - all others are considered unsafe.";
						break;
						case "CAN_USE_ARTIFACT":
							$description="Reagent can be an Artifact. Using [PRESERVE_REAGENT] with this is strongly advised.";
						break;
						case "WORTHLESS_STONE_ONLY":
							$description="Reagent material must be non-economic.";
						break;
						case "ANY_PLANT_MATERIAL":
							$description="Reagent material must be subordinate to a PLANT object.";
						break;
						case "ANY_SILK_MATERIAL":
							$description="Reagent material must have the [SILK] token.";
						break;
						case "ANY_YARN_MATERIAL":
							$description="Reagent material must have the [YARN] token.";
						break;
						case "ANY_SOAP_MATERIAL":
							$description="Reagent material must have the [SOAP] token.";
						break;
						case "ANY_LEATHER_MATERIAL":
							$description="Reagent material must have the [LEATHER] token.";
						break;
						case "ANY_BONE_MATERIAL":
							$description="Reagent material must have the [BONE] token.";
						break;
						case "ANY_STRAND_TISSUE":
							$description="Reagent is made of a tissue having [TISSUE_SHAPE:STRANDS], intended for matching hair and wool. Must be used with [USE_BODY_COMPONENT].";
						break;
						case "ANY_SHELL_MATERIAL":
							$description="Reagent material must have the [SHELL] token.";
						break;
						case "ANY_TOOTH_MATERIAL":
							$description="Reagent material must have the [TOOTH] token.";
						break;
						case "ANY_HORN_MATERIAL":
							$description="Reagent material must have the [HORN] token.";
						break;
						case "ANY_PEARL_MATERIAL":
							$description="Reagent material must have the [PEARL] token.";
						break;
						case "USE_BODY_COMPONENT":
							$description="Reagent must be a body part (CORPSE or CORPSEPIECE).";
						break;
						case "NO_EDGE_ALLOWED":
							$description="Reagent must not have an edge - excludes sharp stones (produced using knapping) and most types of weapon/ammo.";
						break;
						case "NOT_ENGRAVED":
							$description="Reagent has not been engraved (excludes memorial slabs).";
						break;
						case "NOT_IMPROVED":
							$description="Reagent has not been decorated.";
						break;
						case "DOES_NOT_ABSORB":
							$description="Reagent material must have [ABSORPTION:0]";
						break;
						case "FOOD_STORAGE_CONTAINER":
							$description="Reagent is either a BARREL or a TOOL with the FOOD_STORAGE use.";
						break;
						case "HARD_ITEM_MATERIAL":
							$description="Reagent material must have [ITEMS_HARD].";
						break;
						case "NOT_PRESSED":
							$description="Reagent must not be in the SOLID_PRESSED state.";
						break;
						case "METAL_ORE":
							$description="Reagent material must be an ore of the ".$token[1]. ".";
						break;
						case "MIN_DIMENSION":
							$description="Reagent's item dimension must be at least ".$token[1]. ".";
						break;
						case "HAS_TOOL_USE":
							$description="Reagent must be a tool with ".$token[1]. " TOOL_USE value. The reagent's item type must be TOOL:NONE for this to make any sense.";
						break;
						case "PRESERVE_REAGENT":
							$description="Reagent is not destroyed";
						break;
						case "DOES_NOT_DETERMINE_PRODUCT_AMOUNT":
							$description="Reagent quantity is ignored for the purposes of producing extra outputs. Typically used for containers so that stacks of reagents will correctly produce additional outputs.";
						break;
						case "":
						$description="";
						break;
						default:
							$description='<span class="error">Unidentified token '.$token[0].'.</span>';
						break;
						
					}		
					if ($description)
						$tmp.='<abbr title="'.$description.'">'. $token[0][0] .'</abbr>';
					if ($description==='')
						$tmp.='';
				}
				$output[$shop][$i]=$tmp;
			}
			
		return($output);
	}
	
	public static function dfMain (&$parser, $type = ''/*, ...*/)
	{
		global $wgNoWiki, $wgRawPath;
		$wgNoWiki = 0;
		
		if ($type === '') return '<span class="error">Df function has no telepathic abilities. Feed it gently with parameters of your choice.</span>';
		
		// Convert all parameters into $in array
		//$valid_params = explode(', ',
		/*keyTrans*/ //'key, replace, join, '.
		/*else*/ //'filename, option, building, main, nowiki');
		for ($i = 2; $i <= func_num_args()-1; $i++)
		{
			$params[$i-2] = preg_replace("|\n|", '', func_get_arg($i));
			$params[$i-2] = explode('=',$params[$i-2],2);
			if (!(count($params[$i-2]) === 2)) return '<span class="error">Either "=" is missing or too much of them.</span>';
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
		
		if (isset($in['filename']))
		{
			$in['filename'] = str_replace(array('\\','/'), array('',':'), $in['filename']);
			$in['filename'] = self::multiexplode(array(";",":"),$in['filename']);
			$wgRawPath=$in['filename'];
		}
		
		switch ($type)
		{
			//*** Key: key
			case 'key':
				if (!isset($in['key'])) return '<span class="error">Define df:key parameter (key).</span>';
				$output = self::keyTrans($in);
			break;
			
			//*** Load: filename (Just. Load. Raws.)
			case 'load':
				if (!isset($in['filename'])) return '<span class="error">Define df:load parameter (filename).</span>';
				$output = self::loadRaw($in['filename']);
			break;
			
			//*** Buiding: filename, building, option
			case 'building':
				if (!isset($in['filename'], $in['building'], $in['options']))
					return '<span class="error">Define df:building parameters (filename, building, option).</span>';
				$in['option'] = self::multiexplode(array(';',':'),$in['option']);
				$in['building'] = self::multiexplode(array(';',':'),$in['building']);
				$output = self::getBuilding($in);
			break;
			
			//*** Type: 
			case 'type':
			
				if (isset($in['padding'][$i]))
					$in['padding'] = explode('//',$in['padding']);
					echo 'padding='; print_r($in['padding']);
				for ($i=0; $i<=2; $i++)
					if (!isset($in['padding'][$i]))
						$in['padding'][$i]=' $1 ';
				echo 'padding='; print_r($in['padding']);
					
				if (!(isset($in['filename']) or isset($in['data'])) or !isset($in['object']))
					return '<span class="error">Define df:type parameters (filename or data, object).</span>';
				
				if (isset($in['obj_cond']))
				$in['obj_cond'] = self::multiexplode(array(';',':'),$in['obj_cond']);
				
				if (isset($in['tag_cond']))
					$in['tag_cond'] = self::multiexplode(array(';',':'),$in['tag_cond']);
				
				if (isset($in['filename'],$in['data']))
					return '<span class="error">Specify either "data" or "filename".</span>';
					
				if (isset($in['filename']))
				{
					$in['data'] = self::loadRaw($in['filename']);
					unset ($in['filename']);
				}
				
				// echo '$in='; print_r($in); ////
				
				if (isset($in['data']))
					$output = self::getType($in);
			break;
			
			default:
				return '<span class="error">Df function lacks required functionality. Choose supported type instead of "'. $type .'".</span>';
		}
		
		// Output
		if ($wgNoWiki>0)
			return array($output, 'nowiki' => true );
		return $output;
		
	}
}

/* Wiki text
<table class="wikitable sortable">
<tr><th>Header 1</th><th>Header 2</th><th>Header 2</th><th>Header 2</th></tr>
<tr><td align="center">{{#df:type|object=BUILDING_WORKSHOP
|obj_cond=BUILD_ITEM:BLOCKS
|tag_cond=NAME;BUILD_KEY;DIM;BUILD_LABOR
|padding=&://&</td><td align="center">//&</td align="center"></tr><tr><td align="center">
|filename=Masterwork:building_masterwork}}
</td></tr></table>*/