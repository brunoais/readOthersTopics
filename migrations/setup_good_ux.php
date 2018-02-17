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
			array('permission.add', array(self::PERMISSION_NAME, false, false)),
			
			// The new role is supposed to behave just like the standard_access role
			array('custom', array(array($this, 'copy_permissions_from_standard_access'))),
			
			// Setup default value as "yes" for roles that have the "can read" permission
			array('custom', array(array($this, 'apply_permission_to_roles_with_can_read'))),
			
			// Setup default value as "yes" for groups that manually have the "can read" permission
			array('custom', array(array($this, 'apply_permission_to_groups_with_can_read'))),
			
			// Setup default value as "yes" for individuals who manually have the "can read" permission
			array('custom', array(array($this, 'apply_permission_to_users_with_can_read'))),
			
			// Remove permission from new role to make the difference against the copied one
			array('permission.permission_unset', array(self::ROLE_NAME, self::PERMISSION_NAME, 'role')),
			
		);
	}


	public function copy_permissions_from_standard_access()
	{
		$sql = "
		INSERT INTO " . $this->table_prefix . "acl_roles_data (role_id, auth_option_id, auth_setting)
			SELECT (
				SELECT role_id
				FROM " . $this->table_prefix . "acl_roles
				WHERE role_name = '". self::ROLE_NAME . "'
			), auth_option_id, auth_setting
			FROM " . $this->table_prefix . "acl_roles_data
			WHERE role_id = (
					SELECT role_id
					FROM " . $this->table_prefix . "acl_roles
					WHERE role_name = '" . self::PARENT_NATIVE_ROLE_NAME . "'
				)";
	  
		$result = $this->db->sql_query($sql);
		$this->db->sql_freeresult($result);
		
	}

	public function apply_permission_to_roles_with_can_read()
	{
		$sql = "
			INSERT INTO " . $this->table_prefix . "acl_roles_data (role_id, auth_option_id, auth_setting)
			SELECT role_id,
			(
				SELECT auth_option_id
				FROM " . $this->table_prefix . "acl_options
				WHERE auth_option = '" . self::PERMISSION_NAME . "'
			 ), 1
			FROM (
				SELECT role_id
				FROM " . $this->table_prefix . "acl_roles_data
				WHERE auth_option_id = 
						(
							SELECT auth_option_id
							FROM " . $this->table_prefix . "acl_options
							WHERE auth_option = '" . self::PARENT_PERMISSION_NAME . "'
						) AND
						auth_setting = 1
			) AS previous_table
		";
	  
		$result = $this->db->sql_query($sql);
		$this->db->sql_freeresult($result);
	
	}

	public function apply_permission_to_groups_with_can_read()
	{
		
		$sql = "
			INSERT INTO " . $this->table_prefix . "acl_groups (group_id, forum_id, auth_option_id, auth_role_id, auth_setting)
			SELECT group_id, forum_id, 
			(
				SELECT auth_option_id
				FROM " . $this->table_prefix . "acl_options
				WHERE auth_option = '" . self::PERMISSION_NAME . "'
			 ), 0, 1
			FROM (
				SELECT group_id, forum_id
				FROM " . $this->table_prefix . "acl_groups
				WHERE auth_role_id = 0 AND
					forum_id <> 0 AND
					auth_option_id = 
						(
							SELECT auth_option_id
							FROM " . $this->table_prefix . "acl_options
							WHERE auth_option = '" . self::PARENT_PERMISSION_NAME . "'
						) AND
						auth_setting = 1
			) AS previous_table
		";
	  
		$result = $this->db->sql_query($sql);
		$this->db->sql_freeresult($result);
	}

	public function apply_permission_to_users_with_can_read()
	{
		
		$sql = "
			INSERT INTO " . $this->table_prefix . "acl_users (user_id, forum_id, auth_option_id, auth_role_id, auth_setting)
			SELECT user_id, forum_id, 
			(
				SELECT auth_option_id
				FROM " . $this->table_prefix . "acl_options
				WHERE auth_option = '" . self::PERMISSION_NAME . "'
			 ), 0, 1
			FROM (
				SELECT user_id, forum_id
				FROM " . $this->table_prefix . "acl_users
				WHERE auth_role_id = 0 AND
					forum_id <> 0 AND
					auth_option_id = 
						(
							SELECT auth_option_id
							FROM " . $this->table_prefix . "acl_options
							WHERE auth_option = '" . self::PARENT_PERMISSION_NAME . "'
						) AND
						auth_setting = 1
			) AS previous_table
		";
	  
		$result = $this->db->sql_query($sql);
		$this->db->sql_freeresult($result);
	}
}
