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

	'ACL_F_READ_OTHERS_TOPICS' => 'Peut lire les sujets postés par un autre',
	'BRUNOAIS_ROLE_READ_OTHERS_TOPICS' => 'Accès standard sans lecture sujets postés par un autre',
	'BRUNOAIS_ROLE_DESCRIPTION_READ_OTHERS_TOPICS' => 'Peut utiliser les fonctions standards d’un forum sans pouvoir lire les sujets postés par quelqu’un d’autre',

));
