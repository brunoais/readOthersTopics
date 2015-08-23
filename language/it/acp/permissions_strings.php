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

	'ACL_F_READ_OTHERS_TOPICS' => 'Può leggere argomenti aperti da altri',
	'BRUNOAIS_ROLE_READ_OTHERS_TOPICS' => 'Accesso standard tranne argomenti aperti da altri',
	'BRUNOAIS_ROLE_DESCRIPTION_READ_OTHERS_TOPICS' => 'Può usare molte caratteristiche del forum, come per Accesso standard, ma non può visualizzare gli argomenti aperti da altri utenti.',

));
