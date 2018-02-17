<?php

namespace brunoais\readOthersTopics\migrations;

/**
*
* @package phpBB Extension - brunoais readOthersTopics
* @copyright (c) 2015 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

class initial_release extends \phpbb\db\migration\migration
{
	const PERMISSION_NAME = 'f_read_others_topics_brunoais';
	const PARENT_PERMISSION_NAME = 'f_read';
	
	const ROLE_NAME = 'BRUNOAIS_ROLE_READ_OTHERS_TOPICS';
	const ROLE_DESCRIPTION_NAME = 'BRUNOAIS_ROLE_DESCRIPTION_READ_OTHERS_TOPICS';
	
	const PARENT_NATIVE_ROLE_NAME = 'ROLE_FORUM_STANDARD';

	public function update_data()
	{
		return array(
			array('permission.role_add', array(self::ROLE_NAME, 'f_', self::ROLE_DESCRIPTION_NAME)),
			array('permission.add', array(self::PERMISSION_NAME, false, self::PARENT_PERMISSION_NAME)),
			
			// Remove permission from new role to make the difference against the copied one
			array('permission.permission_unset', array(self::ROLE_NAME, self::PERMISSION_NAME, 'role')),
			
		);
	}
}
