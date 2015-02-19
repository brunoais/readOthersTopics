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
			
			'core.report_post_auth'				=> 'phpbb_report_post_auth',
			
			'core.phpbb_content_visibility_get_visibility_sql_before'		=> 'phpbb_content_visibility_get_visibility_sql_before',
			'core.phpbb_content_visibility_get_forums_visibility_before'	=> 'phpbb_content_visibility_get_forums_visibility_before',
			
			
			'core.viewforum_modify_topics_data'						=> 'phpbb_viewforum_modify_topics_data',
			'core.display_forums_modify_template_vars'				=> 'phpbb_display_forums_modify_template_vars',
			'core.viewtopic_before_f_read_check'					=> 'phpbb_viewtopic_before_f_read_check',
			
			
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
		if(in_array($event['mode'], array('reply', 'quote', 'edit', 'delete', 'bump'), true)){
			
			$permissionResult = $this->permissionEvaluate(array(
				'forum_id' => $event['forum_id'],
				'topic_id' => $event['topic_id'],
				'post_id' => $event['post_id'],
			));
			
			if($permissionResult === 'NO_READ_OTHER'){
				trigger_error('NOT_AUTHORISED');
			}
		}
	}
	
	public function phpbb_report_post_auth($event){

		$permissionResult = $this->permissionEvaluate(array(
			'forum_id' => $event['forum_data']['forum_id'],
			'topic_id' => $event['report_data']['topic_id'],
			'post_id' => $event['report_data']['post_id'],
			'topic_poster' => $event['report_data']['topic_poster'],
			'topic_type' => $event['report_data']['topic_type'],
		));
		
		if($permissionResult === 'NO_READ_OTHER'){
			trigger_error('POST_NOT_EXIST');
		}
	}
	
	
	public function phpbb_content_visibility_get_visibility_sql_before($event){
		
		if(!$this->auth->acl_get('f_read_others_topics_brunoais', $event['forum_id'])){
			if($event['mode'] === 'topic'){
				$event['where_sql'] .= ' ' . $event['table_alias'] . 'topic_poster = ' . (int) $this->user->data['user_id'] . ' AND ';
			}
		}
	}
	
	public function phpbb_content_visibility_get_forums_visibility_before($event){
		
		// If the event mode is 'post', there's nothing I can do here.
		if($event['mode'] === 'topic'){
			$forum_ids = $event['forum_ids'];
			$fullAccessForumIDs = array();
			
			foreach($forum_ids AS $forum_id){
				// var_dump($this->auth->acl_get('f_read_others_topics_brunoais', $forum_id));
				if($this->auth->acl_get('f_read_others_topics_brunoais', $forum_id)){
						$fullAccessForumIDs[] = $forum_id;
				}
			}
			
			if(sizeof($fullAccessForumIDs) === sizeof($forum_ids)){
				// Nothing to filter
				return;
			}
			
			$event['where_sql'] .= ' (' . $this->db->sql_in_set($event['table_alias'] . 'forum_id', $fullAccessForumIDs) . '
				OR ' . $event['table_alias'] . 'topic_poster = ' . (int) $this->user->data['user_id'] . ' ) AND ';
			
		}
		
	}
	
	
	
	// public function phpbb_viewforum_modify_topics_data($event){
		
		// // For now, there's no good way of doing this... Maybe there will be one later
		// $this->template->assign_vars(array(
			// 'TOTAL_TOPICS'	=> false,
		// ));
		
	// }
	
	
	public function phpbb_viewforum_modify_topics_data($event){
		
		$forumIds = array();
		$forumIDVsContent = array();
		$topicIdToRemove = array();
		
		$topic_list = $event['topic_list'];
		$rowset = $event['rowset'];
		$total_topic_count = $event['total_topic_count'];
		
		foreach($rowset AS $rowElement){
			$forumIds[] = $rowElement['forum_id'];
			$forumIDVsContent[$rowElement['forum_id']][] = $rowElement;
		}
		
		$forumIds = array_unique($forumIds, SORT_NUMERIC);
		
		$limitedAccessForumIDs = array();

		foreach($forumIds AS $forumId){
			if(!$this->auth->acl_get('f_read_others_topics_brunoais', $forumId)){
				$limitedAccessForumIDs[] = $forumId;
			}
		}
		
		
		foreach($limitedAccessForumIDs AS $limitedAccessForumID){
			foreach($forumIDVsContent[$limitedAccessForumID] AS $thisTopicData){
				if($thisTopicData['topic_poster'] != $this->user->data['user_id'] &&					
						$thisTopicData['topic_type'] != POST_ANNOUNCE &&
						$thisTopicData['topic_type'] != POST_GLOBAL
					){
					$topicIdToRemove[] = $thisTopicData['topic_id'];
					unset($rowset[$thisTopicData['topic_id']]);
					$total_topic_count--;
				}
			}
		}
		
		$topic_list = array_diff($topic_list, $topicIdToRemove);
		
		$event['topic_list'] = $topic_list;
		$event['rowset'] = $rowset;
		$event['total_topic_count'] = $total_topic_count;
		
		$this->template->assign_vars(array(
			'TOTAL_TOPICS'	=> false,
		));
		
		// var_dump($event['rowset']);
		
		
		// $permissionResult = $this->permissionEvaluate(array(
			// 'forum_id' => $event['forum_id'],
			// 'topic_id' => $event['topic_id'],
			// 'post_id' => $event['post_id'],
			// 'topic_poster' => $event['topic_poster'],
			// 'topic_type' => $event['topic_type'],
		// ));
		
		
	}
	
	public function phpbb_display_forums_modify_template_vars($event){
		
		if(!$this->auth->acl_get('f_read_others_topics_brunoais', $event['forum_row']['FORUM_ID'])){
			$forum_row = $event['forum_row'];
			$forum_row['TOPICS'] = '-';
			$forum_row['POSTS'] = '-';
			$forum_row['LAST_POSTER_FULL'] = '-';
			$forum_row['U_LAST_POST'] = '#';
			// $forum_row['S_DISPLAY_SUBJECT'] = false;
			$forum_row['LAST_POST_SUBJECT'] = '*Classified information*';
			$forum_row['LAST_POST_SUBJECT_TRUNCATED'] = '*Classified information*';
			// $forum_row['LAST_POST_TIME'] = '';
			$forum_row['LAST_POST_TIME'] = '&nbsp;
<script>
	var script = document.currentScript || (function() {
		var scripts = document.getElementsByTagName("script");
		return scripts[scripts.length - 1];
	})();
	script.parentNode.parentNode.innerHTML = "<span>Classified information</span>";
</script>
';
			$event['forum_row'] = $forum_row;
		}
		
	}

	public function phpbb_viewtopic_before_f_read_check($event){
		
		$permissionResult = $this->permissionEvaluate(array(
			'forum_id' => $event['forum_id'],
			'topic_id' => $event['topic_id'],
			'post_id' => $event['post_id'],
			'topic_poster' => $event['topic_data']['topic_poster'],
			'topic_type' => $event['topic_data']['topic_type'],
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
			
			if(
				$info['topic_poster'] != $this->user->data['user_id'] &&
				$info['topic_type'] != POST_ANNOUNCE &&
				$info['topic_type'] != POST_GLOBAL
				){
				return 'NO_READ_OTHER';
			}
		}
		
		return true;
		
	}

}
