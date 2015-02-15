<?php
/**
*
* @package phpBB Extension - brunoais readOthersTopics
* @copyright (c) 2015 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace brunoais\readOthersTopics\event;

/**
* @ignore
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class main_listener implements EventSubscriberInterface
{
	static public function getSubscribedEvents()
	{
		return array(
			'core.ucp_pm_compose_compose_pm_basic_info_query_before'		=> 'phpbb_ucp_pm_compose_compose_pm_basic_info_query_before',
			'core.ucp_pm_compose_quotepost_query_after'						=> 'phpbb_ucp_pm_compose_quotepost_query_after',
			
			'core.modify_posting_auth'			=> 'phpbb_modify_posting_auth',
			
			'core.viewtopic_before_f_read_check'						=> 'phpbb_viewtopic_before_f_read_check',
		);
	}

	protected $infoStorage;
	
	/* @var \phpbb\auth\auth */
	protected $auth;
	
	/* @var \phpbb\user */
	protected $user;
	
	/* @var \phpbb\db\driver\driver_interface */
	protected $db;
	
	/* @var \phpbb\template\template */
	protected $template;
	
	/* Tables */
	public $forums_table;
	public $topics_table;
	public $posts_table;

	/**
	* Constructor
	*
	* @param	\phpbb\auth\auth					$auth	Auth object
	* @param	\phpbb\user							$user	User object
	* @param	\phpbb\db\driver\driver_interface	$db		Database object
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\db\driver\driver_interface $db, \phpbb\template\template $template, \phpbb\user $user, $forums_table, $topics_table, $posts_table)
	{
		$this->auth = $auth;
		$this->db = $db;
		$this->user = $user;
		$this->template = $template;
		$this->forums_table = $forums_table;
		$this->topics_table = $topics_table;
		$this->posts_table = $posts_table;
		
		$this->infoStorage = array();
	}

	public function phpbb_ucp_pm_compose_compose_pm_basic_info_query_before($event){

		if($event['action'] === 'quotepost'){
			$sql = $event['sql'];
			
			$sql = explode(' ', $sql, 2);
			$sql = $sql[0] . ' t.topic_poster, ' . $sql[1];
			$event['sql'] = $sql;
		}
	}
	
	public function phpbb_ucp_pm_compose_quotepost_query_after($event){

		$permissionResult = $this->permissionEvaluate(array(
			'forum_id' => $event['post']['forum_id'],
			'post_id' => $event['msg_id'],
			'topic_poster' => $event['topic_poster'],
		));
		
		if($permissionResult === 'NO_READ_OTHER'){
			trigger_error('NOT_AUTHORISED');
		}
	}
	
	public function phpbb_modify_posting_auth($event){

		$permissionResult = $this->permissionEvaluate(array(
			'forum_id' => $event['forum_id'],
			'topic_id' => $event['topic_id'],
			'post_id' => $event['post_id'],
		));
		
		if($permissionResult === 'NO_READ_OTHER'){
			trigger_error('NOT_AUTHORISED');
		}
	}
	
	public function phpbb_viewtopic_before_f_read_check($event){
		
		$permissionResult = $this->permissionEvaluate(array(
			'forum_id' => $event['forum_id'],
			'topic_id' => $event['topic_id'],
			'post_id' => $event['post_id'],
			'topic_poster' => $event['topic_poster'],
		));
		
		
		if($permissionResult === 'NO_READ_OTHER'){
			$this->user->add_lang_ext('brunoais\readOthersTopics', 'common');
			$this->accessFailed();
		}
		
		// If all checkout, I already did the f_read check and it passed, no need to do it again.
		$event['overrides_f_read_check'] = $permissionResult === true;
		
	}
	
	
	
	
	//
	// Auxiliary functions
	//

	private function accessFailed(){
		trigger_error('SORRY_AUTH_READ_OTHER');
	}

	private function getForumIdAndPosterFromTopic(&$info){
		$sql = 'SELECT forum_id, topic_poster
			FROM ' . $this->topics_table . '
			WHERE topic_id = ' . (int) $info['topic_id'];
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		
		$info['forum_id'] = $row['forum_id'];
		$info['topic_poster'] = $row['topic_poster'];
		
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

	private function getPosterFromTopicId(&$info){
		
		$sql = 'SELECT topic_poster
			FROM ' . $this->topics_table . '
			WHERE topic_id = ' . (int) $info['topic_id'];
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		
		$info['topic_poster'] = $row['topic_poster'];
		
		$this->db->sql_freeresult($result);
	}

	private function permissionEvaluate($info)
	{
		if(!isset($info['forum_id'])){
			if(isset($info['topic_id'])){
				$this->getForumIdAndPosterFromTopic($info);
			}else if(!isset($info['post_id'])){
				$this->getForumIdAndTopicFromPost($info);
			}
		}
		
		if(!$this->auth->acl_get('f_read', $info['forum_id'])){
			return 'NO_READ';
		}
		
		
		if(!$this->auth->acl_get('f_read_others_topics_brunoais', $info['forum_id'])){
			if($this->user->data['user_id'] == ANONYMOUS){
				return 'NO_READ_OTHER';
			}
			
			if(!$info['topic_poster']){
				$this->getPosterFromTopicId($info);
			}
			
			if($info['topic_poster'] != $this->user->data['user_id']){
				return 'NO_READ_OTHER';
			}
		}
		
		return true;
		
	}

}
