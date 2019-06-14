<?php
/**
*
* @package phpBB Extension - brunoais readOthersTopics
* @copyright (c) 2015 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
* Translated By : Bassel Taha Alhitary <https://www.alhitary.net>
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

	'ACL_F_READ_OTHERS_TOPICS' => 'يستطيع قراءة مواضيع الأعضاء',
	'BRUNOAIS_ROLE_READ_OTHERS_TOPICS' => 'تصفح متوسط بدون قراءة مواضيع الأعضاء',
	'BRUNOAIS_ROLE_DESCRIPTION_READ_OTHERS_TOPICS' => 'يستطيع استخدام معظم خصائص المنتدى مثل التصفح المتوسط تماماً ولكن لا يستطيع قراءة المواضيع التي تم نشرها بواسطة الأعضاء الآخرين.',

));
