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
			'core.ucp_pm_compose_compose_pm_basic_info_query_before'	=> 'phpbb_ucp_pm_compose_compose_pm_basic_info_query_before',
			'core.ucp_pm_compose_quotepost_query_after'					=> 'phpbb_ucp_pm_compose_quotepost_query_after',

			'core.modify_posting_auth'								=> 'phpbb_modify_posting_auth',

			'core.report_post_auth'									=> 'phpbb_report_post_auth',

			'core.get_logs_main_query_before'						=> 'phpbb_get_logs_main_query_before',

			'core.phpbb_content_visibility_get_visibility_sql_before'		=> 'phpbb_content_visibility_get_visibility_sql_before',
			'core.phpbb_content_visibility_get_forums_visibility_before'	=> 'phpbb_content_visibility_get_forums_visibility_before',


			'core.display_forums_after'								=> 'phpbb_display_forums_after',
			'core.viewforum_get_topic_data'							=> 'phpbb_viewforum_get_topic_data',
			'core.viewforum_get_topic_ids_data'						=> 'phpbb_viewforum_get_topic_ids_data',
			'core.display_forums_modify_template_vars'				=> 'phpbb_display_forums_modify_template_vars',
			'core.viewtopic_before_f_read_check'					=> 'phpbb_viewtopic_before_f_read_check',

		);
	}

	protected $infoStorage;

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
	public function __construct(\phpbb\auth\auth $auth, \phpbb\content_visibility $content_visibility, \phpbb\db\driver\driver_interface $db, \phpbb\template\template $template, \phpbb\user $user,
	\brunoais\readOthersTopics\shared\accesses $accesses, \brunoais\readOthersTopics\shared\permission_evaluation $permission_evaluation, 
	$forums_table, $topics_table, $posts_table)
	{
		$this->auth = $auth;
		$this->phpbb_content_visibility = $content_visibility;
		$this->db = $db;
		$this->template = $template;
		$this->user = $user;
		$this->permission_evaluation = $permission_evaluation;
		$this->accesses = $accesses;
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

		$permissionResult = $this->permission_evaluation->permissionEvaluate(array(
			'forum_id' => $event['post']['forum_id'],
			'post_id' => $event['msg_id'],
			'topic_poster' => $event['topic_poster'],
		));

		$accesses = $this->accesses;
		if($permissionResult === $accesses::NO_READ_OTHER){
			trigger_error('NOT_AUTHORISED');
		}
	}

	public function phpbb_modify_posting_auth($event){
		if(in_array($event['mode'], array('reply', 'quote', 'edit', 'delete', 'bump'), true)){

			$permissionResult = $this->permission_evaluation->permissionEvaluate(array(
				'forum_id' => $event['forum_id'],
				'topic_id' => $event['topic_id'],
				'post_id' => $event['post_id'],
			));

			$accesses = $this->accesses;
			if($permissionResult === $accesses::NO_READ_OTHER){
				trigger_error('NOT_AUTHORISED');
			}
		}
	}

	public function phpbb_report_post_auth($event){

		$permissionResult = $this->permission_evaluation->permissionEvaluate(array(
			'forum_id' => $event['forum_data']['forum_id'],
			'topic_id' => $event['report_data']['topic_id'],
			'post_id' => $event['report_data']['post_id'],
			'topic_poster' => $event['report_data']['topic_poster'],
			'topic_type' => $event['report_data']['topic_type'],
		));

		$accesses = $this->accesses;
		if($permissionResult === $accesses::NO_READ_OTHER){
			trigger_error('POST_NOT_EXIST');
		}
	}


	public function phpbb_get_logs_main_query_before($event){

		$permissionResult = NULL;
		$fullAccessForumIDs = array();

		if($event['log_type'] == LOG_MOD){
			if ($event['topic_id']){
				$permissionResult = $this->permission_evaluation->permissionEvaluate(array(
					'topic_id' => $event['topic_id'],
				));
				$accesses = $this->accesses;
				if($permissionResult === $accesses::FULL_READ){
					return;
				}
			}else if (is_array($event['forum_id'])){
				$forum_ids = $event['forum_id'];
				$fullAccessForumIDs = array();
				foreach($forum_ids AS $forum_id){
					if($this->auth->acl_get('f_read_others_topics_brunoais', $forum_id)){
							$fullAccessForumIDs[] = $forum_id;
					}
				}

				if(sizeof($fullAccessForumIDs) === sizeof($forum_ids)){
					// Nothing to filter
					return;
				}
			}else if ($event['forum_id']){
				if($this->auth->acl_get('f_read_others_topics_brunoais', $event['forum_id'])){
					return;
				}
			}else{
				$forum_ids = array_values(array_intersect(get_forum_list('f_read'), get_forum_list('m_')));
				$fullAccessForumIDs = array();
				foreach($forum_ids AS $forum_id){
					if($this->auth->acl_get('f_read_others_topics_brunoais', $forum_id)){
							$fullAccessForumIDs[] = $forum_id;
					}
				}
				if(sizeof($fullAccessForumIDs) === sizeof($forum_ids)){
					// Nothing to filter
					return;
				}
			}

			$from_sql = $event['get_logs_sql_ary']['FROM'];
			$where_sql = $event['get_logs_sql_ary']['WHERE'];

			if(!isset($from_sql[$this->topics_table])){
				$from_sql[$this->topics_table] = 't';
				$where_sql = 't.topic_id = l.topic_id
				AND '. $where_sql;
			}
			$where_sql = '(' . $this->db->sql_in_set('t.forum_id', $fullAccessForumIDs, false, true) . '
			OR t.topic_poster = ' . (int) $this->user->data['user_id'] . ' ) AND '
			. $where_sql;


			$varHold = $event['get_logs_sql_ary'];
			$varHold['FROM'] = $from_sql;
			$varHold['WHERE'] = $where_sql;
			$event['get_logs_sql_ary'] = $varHold;

		}
	}

	public function phpbb_content_visibility_get_visibility_sql_before($event){

		if(!$this->auth->acl_get('f_read_others_topics_brunoais', $event['forum_id'])){
			if($event['mode'] === 'topic'){
				$event['where_sql'] .= ' (' . $event['table_alias'] . ' topic_poster = ' . (int) $this->user->data['user_id'] . '
					OR topic_type = ' . POST_GLOBAL . '
					OR topic_type = ' . POST_ANNOUNCE . '
				)
				AND ';
			}
		}
	}

	public function phpbb_content_visibility_get_forums_visibility_before($event){

		// If the event mode is 'post', there's nothing I can do here.
		if($event['mode'] === 'topic'){
			$forum_ids = $event['forum_ids'];
			$fullAccessForumIDs = array();

			foreach($forum_ids AS $forum_id){
				if($this->auth->acl_get('f_read_others_topics_brunoais', $forum_id)){
						$fullAccessForumIDs[] = $forum_id;
				}
			}

			if(sizeof($fullAccessForumIDs) === sizeof($forum_ids)){
				// Nothing to filter
				return;
			}

			$event['where_sql'] .= ' (' . $this->db->sql_in_set($event['table_alias'] . 'forum_id', $fullAccessForumIDs, false, true) . '
				OR ' . $event['table_alias'] . ' topic_poster = ' . (int) $this->user->data['user_id'] . ' ) AND ';

		}

	}

	public function phpbb_display_forums_after($event){

		$active_forum_ary = $event['active_forum_ary'];

		if(empty($active_forum_ary['exclude_forum_id'])){
			if(empty($active_forum_ary['forum_id'])){
				$forum_ids = array();
			}else{
				$forum_ids = $active_forum_ary['forum_id'];
			}
		}else{
			$forum_ids = array_diff($active_forum_ary['forum_id'], $active_forum_ary['exclude_forum_id']);
		}

		$fullAccessForumIDs = array();
		foreach($forum_ids as $forum_id){
			if($this->auth->acl_get('f_read_others_topics_brunoais', $forum_id)){
				$fullAccessForumIDs[] = $forum_id;
			}
		}

		$this->infoStorage['ActiveTopicIds']['fullAccess'] = $fullAccessForumIDs;
		$this->infoStorage['ActiveTopicIds']['restrictedAccess'] = array_diff($forum_ids, $fullAccessForumIDs);

	}

	public function phpbb_viewforum_get_topic_data($event){

		if(!$event['sort_days']){

			$sql = 'SELECT COUNT(topic_id) AS num_topics
				FROM ' . TOPICS_TABLE . '
				WHERE
					topic_type = ' . POST_GLOBAL . "
					OR (
						forum_id = {$event['forum_id']}
						AND (
							topic_type = " . POST_ANNOUNCE . '
						 OR ' . $this->phpbb_content_visibility->get_visibility_sql('topic', $event['forum_id']) . '
					))';
			$result = $this->db->sql_query($sql);
			$event['topics_count'] = (int) $this->db->sql_fetchfield('num_topics');
			$this->db->sql_freeresult($result);

		}

	}


	public function phpbb_viewforum_get_topic_ids_data($event){

		if(	$event['forum_data']['forum_type'] != FORUM_POST &&
			strpos($event['sql_where'], 't.forum_id IN') === 0 &&
			!empty($this->infoStorage['ActiveTopicIds']['restrictedAccess'])){

			$sql_ary = $event['sql_ary'];

			$sql_ary['WHERE'] .= '
				AND (' . $this->db->sql_in_set('t.forum_id', $this->infoStorage['ActiveTopicIds']['fullAccess'], false, true) . '
					OR t.topic_poster = ' . (int) $this->user->data['user_id'] . ' )';

			$event['sql_ary'] = $sql_ary;
		}
	}


	public function phpbb_display_forums_modify_template_vars($event){

		if(!$this->auth->acl_get('f_read_others_topics_brunoais', $event['forum_row']['FORUM_ID'])){
			$this->user->add_lang_ext('brunoais/readOthersTopics', 'common');
			
			$forum_row = $event['forum_row'];
			$forum_row['TOPICS'] = '-';
			$forum_row['POSTS'] = '-';
			$forum_row['LAST_POSTER_FULL'] = '-';
			$forum_row['U_LAST_POST'] = '#';
			// $forum_row['S_DISPLAY_SUBJECT'] = false;
			$forum_row['LAST_POST_SUBJECT'] = '*' . $this->user->lang('SORRY_CLASSIFIED_INFORMATION') . '*';
			$forum_row['LAST_POST_SUBJECT_TRUNCATED'] = '*' . $this->user->lang('SORRY_CLASSIFIED_INFORMATION') . '*';
			$forum_row['LAST_POST_TIME'] = '-';
			$forum_row['S_IS_CLASSIFIED'] = true;
			$event['forum_row'] = $forum_row;
		}

	}

	public function phpbb_viewtopic_before_f_read_check($event){

		$permissionResult = $this->permission_evaluation->permissionEvaluate(array(
			'forum_id' => $event['forum_id'],
			'topic_id' => $event['topic_id'],
			'post_id' => $event['post_id'],
			'topic_poster' => $event['topic_data']['topic_poster'],
			'topic_type' => $event['topic_data']['topic_type'],
		));

		$accesses = $this->accesses;
		if($permissionResult === $accesses::NO_READ_OTHER){
			$this->permission_evaluation->accessFailed();
		}

		// If all checkout, I already did the f_read check and it passed, no need to do it again.
		$event['overrides_f_read_check'] = $permissionResult === true;

	}

}

