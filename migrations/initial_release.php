<?php

/**
*
* @package phpBB Extension - brunoais readOthersTopics
* @copyright (c) 2015 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

class db_changes extends \phpbb\db\migration\migration
{
	const PERMISSION_NAME = 'f_brunoais_read_others_topics';
	
	const ROLE_NAME = 'BRUNOAIS_ROLE_READ_OTHERS_TOPICS';
	const ROLE_DESCRIPTION_NAME = 'BRUNOAIS_ROLE_READ_OTHERS_TOPICS_EXPLAIN';
	
	const PARENT_NATIVE_ROLE_NAME = 'ROLE_FORUM_STANDARD';

	public function update_data()
	{
		return array(
			array('permission.role_add', array(self::ROLE_NAME, 'f_', self::ROLE_DESCRIPTION_NAME)),
			array('permission.add', array(self::PERMISSION_NAME, false, false)),
			
			// Setup correct position for the new role
			array('custom', array(array($this, 'make_place_for_new_role'))),
			array('custom', array(array($this, 'move_new_role_to_after_standard_permissions'))),
			array('custom', array(array($this, 'copy_permissions_from_standard_access'))),
			
			// Update permission difference for the new role
			array('permission.permission_set', array(self::ROLE_NAME, self::PERMISSION_NAME, 'role', true)),
		);
	}

	public function make_place_for_new_role()
	{
		// Move all other roles for "f_" after self::PARENT_NATIVE_ROLE_NAME 1 value below
		$sql = 
		"UPDATE phpbb_acl_roles
		SET role_order = role_order + 1
		WHERE 
			(role_order > 
				(
					SELECT role_order
					FROM (
						SELECT role_order
							FROM phpbb_acl_roles
							WHERE role_name = '" . self::PARENT_NATIVE_ROLE_NAME . "'
					) AS original
					WHERE 1
				)
			) AND (
			role_type = 'f_'
			)
		";
		$result = $this->db->sql_query($sql);
		$this->db->sql_freeresult($result);
		
	}

	public function move_new_role_to_after_standard_permissions()
	{
		// Move my new role to under self::PARENT_NATIVE_ROLE_NAME
		$sql = "
		UPDATE phpbb_acl_roles
		SET role_order = (
			SELECT role_order +1
				FROM (
					SELECT role_order
						FROM phpbb_acl_roles
						WHERE role_name = '" . self::PARENT_NATIVE_ROLE_NAME . "'
					 ) AS source_val
				WHERE 1
			)
		WHERE role_name = 'BRUNOAIS_ROLE_READ_OTHERS_TOPICS'
		";
		
		$result = $this->db->sql_query($sql);
		$this->db->sql_freeresult($result);
	}

	public function copy_permissions_from_standard_access()
	{
		$sql = "
		INSERT INTO phpbb_acl_roles_data (role_id, auth_option_id, auth_setting)
			SELECT (
				SELECT role_id
				FROM phpbb_acl_roles
				WHERE role_name = 'BRUNOAIS_ROLE_READ_OTHERS_TOPICS'
			), auth_option_id, auth_setting
			FROM phpbb_acl_roles_data
			WHERE role_id = (
					SELECT role_id
					FROM phpbb_acl_roles
					WHERE role_name = 'ROLE_FORUM_STANDARD'
				)";
	  
		$result = $this->db->sql_query($sql);
		$this->db->sql_freeresult($result);
		
	}
}