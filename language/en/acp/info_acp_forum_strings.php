<?php
/**
*
* @package phpBB Extension - brunoais readOthersTopics
* @copyright (c) 2015 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.

$lang = array_merge($lang, array(
	'BRUNOAIS_TRUE_LAST_ACCESSIBLE' => 'Always display visible last posted post',
	'BRUNOAIS_TRUE_LAST_ACCESSIBLE_EXPLAIN' => '(Brunoais read other) If set to yes, do go extra mile to deliver the actual last post the user can see. Set to no if you do not want it or if it takes too much resources',
));
