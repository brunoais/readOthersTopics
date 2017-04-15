<?php
/**
*
* @package phpBB Extension - brunoais readOthersTopics
* @copyright (c) 2015 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace brunoais\readOthersTopics\shared;

/**
* Auxiliary content
*/
	
class permission_evaluation
{

	/* @var \phpbb\auth\auth */
	protected $auth;

	/* @var \phpbb\user */
	protected $user;

	/* @var \phpbb\content_visibility */
	protected $phpbb_content_visibility;

	/* @var \phpbb\db\driver\driver_interface */
	protected $db;

	/* @var \phpbb\template\template */
	protected $template;

	/* @var \brunoais\readOthersTopics\shared\permission_evaluation */
	protected $permission_evaluation;
	
	/* @var \brunoais\readOthersTopics\shared\accesses */
	protected $accesses;

	/* Tables */
	public $forums_table;
	public $topics_table;
	public $posts_table;

	/**
	* Constructor
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\content_visibility $content_visibility, \phpbb\db\driver\driver_interface $db, \phpbb\template\template $template, \phpbb\user $user, \brunoais\readOthersTopics\shared\accesses $accesses,
	$forums_table, $topics_table, $posts_table)
	{
		$this->auth = $auth;
		$this->phpbb_content_visibility = $content_visibility;
		$this->db = $db;
		$this->template = $template;
		$this->user = $user;
		$this->accesses = $accesses;
		$this->forums_table = $forums_table;
		$this->topics_table = $topics_table;
		$this->posts_table = $posts_table;

		$this->infoStorage = array();
	}
	
	/**
	 * Returns whether the user has:
	 * - Full read access: accesses::FULL_READ
	 * - Can only read own topics: accesses::NO_READ_OTHER
	 * - No read access: accesses::NO_READ
	 *
	 * from the input; an associative array with all info you can give of:
	 * forum_id, topic_id, post_id, topic_type, topic_poster
	 *
	 * Any missing info is automatically checked with a database search
	 */
	public function permissionEvaluate($info)
	{
		if(empty($info['forum_id'])){
			if(!empty($info['topic_id'])){
				$this->getForumIdAndPosterFromTopic($info);
			}else if(!empty($info['post_id'])){
				$this->getForumIdAndTopicFromPost($info);
			}
		}

		if(!$this->auth->acl_get('f_read', $info['forum_id'])){
			return accesses::NO_READ;
		}


		if(!$this->auth->acl_get('f_read_others_topics_brunoais', $info['forum_id'])){
			if($this->user->data['user_id'] == ANONYMOUS){
				return accesses::NO_READ_OTHER;
			}

			if(
				isset($info['topic_type']) &&
				(
					$info['topic_type'] == POST_ANNOUNCE ||
					$info['topic_type'] == POST_GLOBAL
				)
				){
				return true;
			}

			if(!isset($info['topic_poster'])){
				$this->getPosterAndTypeFromTopicId($info);
			}

			if($info['topic_poster'] != $this->user->data['user_id']){
				if(!isset($info['topic_type'])){
					$this->getPosterAndTypeFromTopicId($info);
				}
				if(
					$info['topic_type'] != POST_ANNOUNCE &&
					$info['topic_type'] != POST_GLOBAL
					){
					return accesses::NO_READ_OTHER;
				}
			}
		}

		return accesses::FULL_READ;

	}
	
	private function accessFailed(){
		$this->user->add_lang_ext('brunoais/readOthersTopics', 'common');
		trigger_error('SORRY_AUTH_READ_OTHER');
	}

	private function getForumIdAndPosterFromTopic(&$info){
		$sql = 'SELECT forum_id, topic_poster, topic_type
				FROM ' . $this->topics_table . '
				WHERE topic_id = ' . (int) $info['topic_id'];
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);

		$info['forum_id'] = $row['forum_id'];
		$info['topic_poster'] = $row['topic_poster'];
		$info['topic_type'] = $row['topic_type'];

		$this->db->sql_freeresult($result);
	}

	private function getForumIdAndTopicFromPost(&$info){
		$sql = 'SELECT forum_id, topic_id
				FROM ' . $this->posts_table . '
				WHERE post_id = ' . (int) $info['post_id'];
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);

		$info['forum_id'] = $row['forum_id'];
		$info['topic_id'] = $row['topic_id'];

		$this->db->sql_freeresult($result);
	}

	private function getPosterAndTypeFromTopicId(&$info){
		$sql = 'SELECT topic_poster, topic_type
				FROM ' . $this->topics_table . '
				WHERE topic_id = ' . (int) $info['topic_id'];
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);

		$info['topic_poster'] = $row['topic_poster'];
		$info['topic_type'] = $row['topic_type'];

		$this->db->sql_freeresult($result);
	}


}

