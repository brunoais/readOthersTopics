<?php
/**
*
* @package phpBB Extension - brunoais readOthersTopics
* @copyright (c) 2015 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
* Brazilian Portuguese translation by eunaumtenhoid [2019][ver 1.1.0] (https://github.com/phpBBTraducoes)
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

	'ACL_F_READ_OTHERS_TOPICS' => 'Pode ler tópicos iniciados por terceiros',
	'BRUNOAIS_ROLE_READ_OTHERS_TOPICS' => 'Acesso padrão sem tópicos criados por terceiros',
	'BRUNOAIS_ROLE_DESCRIPTION_READ_OTHERS_TOPICS' => 'Pode usar a maioria dos recursos do fórum como o acesso padrão, exceto a exibição de tópicos iniciados por outros usuários.',

));
