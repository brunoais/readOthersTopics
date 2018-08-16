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

	'ACL_F_READ_OTHERS_TOPICS' => 'Puede leer temas iniciados por otros',
	'BRUNOAIS_ROLE_READ_OTHERS_TOPICS' => 'Acceso estándar sin ver temas iniciados por otros',
	'BRUNOAIS_ROLE_DESCRIPTION_READ_OTHERS_TOPICS' => 'Puede usar la mayoría de las características del foro al igual que el acceso estándar, excepto ver temas iniciados por otros usuarios.',
));
