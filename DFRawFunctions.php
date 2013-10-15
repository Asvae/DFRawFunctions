<?php
/*
 * DFRawFunctions extension by Quietust
 * Dwarf Fortress Raw parser functions
 */

if (!defined('MEDIAWIKI'))
{
	echo "This file is an extension of the MediaWiki software and cannot be used standalone\n";
	die(1);
}

/*
 * Configuration
 * These may be overridden in LocalSettings.php
 */

// Whether or not to allow loading raws from disk
$wgDFRawEnableDisk = true;

// The directory which contains the raw folders and files
$wgDFRawPath = dirname(__FILE__) .'/raws';

/*
 * Extension Logic - do not change anything below this line
 */

$wgExtensionCredits['parserhook'][] = array(
	'path'           => __FILE__,
	'name'           => 'DFRawFunctions',
	'author'         => 'Quietust',
	'url'            => 'http://df.magmawiki.com/index.php/User:Quietust',
	'description'    => 'Dwarf Fortress Raw parser functions',
	'version'        => '1.5',
);

$wgAutoloadClasses['cMain'] = dirname(__FILE__) . '/cMain.php';
$wgAutoloadClasses['cRaw'] = dirname(__FILE__) . '/cRaw.php';


$wgHooks['ParserFirstCallInit'][] = 'efDFRawFunctions_Initialize';
$wgHooks['LanguageGetMagic'][] = 'efDFRawFunctions_RegisterMagicWords';

function efDFRawFunctions_Initialize (&$parser)
{
	$parser->setFunctionHook('df',		'cMain::dfMain');
	$parser->setFunctionHook('delay',		'cMain::delay');
	$parser->setFunctionHook('eval',		'cMain::evaluate');
	
	return true;
}

function efDFRawFunctions_RegisterMagicWords (&$magicWords, $langCode)
{
	$magicWords['df']		= array(0, 'df');
	$magicWords['delay']			= array(0, 'delay');
	$magicWords['eval']				= array(0, 'eval');
	
	return true;
}