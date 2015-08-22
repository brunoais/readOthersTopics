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
			
			'core.get_logs_main_query_before'			=> 'phpbb_get_logs_main_query_before',
			
			'core.mcp_global_f_read_auth_after'						=> 'phpbb_mcp_global_f_read_auth_after',
			'core.mcp_reports_get_reports_query_before'				=> 'phpbb_mcp_reports_get_reports_query_before',
			'core.mcp_sorting_query_before'				=> 'phpbb_mcp_sorting_query_before',
			
			'core.mcp_front_queue_unapproved_total_before'			=> 'phpbb_mcp_front_queue_unapproved_total_before',
			'core.mcp_front_view_queue_postid_list_after'			=> 'phpbb_mcp_front_view_queue_postid_list_after',
			'core.mcp_front_reports_count_query_before'			=> 'phpbb_mcp_front_reports_count_query_before',
			'core.mcp_front_reports_listing_query_before'			=> 'phpbb_mcp_front_reports_listing_query_before',
			
			'core.phpbb_content_visibility_get_visibility_sql_before'		=> 'phpbb_content_visibility_get_visibility_sql_before',
			'core.phpbb_content_visibility_get_forums_visibility_before'	=> 'phpbb_content_visibility_get_forums_visibility_before',
			
			
			'core.display_forums_after'								=> 'phpbb_display_forums_after',
			'core.viewforum_get_topic_data'							=> 'phpbb_viewforum_get_topic_data',
			'core.viewforum_get_topic_ids_data'						=> 'phpbb_viewforum_get_topic_ids_data',
			'core.display_forums_modify_template_vars'				=> 'phpbb_display_forums_modify_template_vars',
			'core.viewtopic_before_f_read_check'					=> 'phpbb_viewtopic_before_f_read_check',
			
			'core.search_modify_rowset'								=> 'search_modify_rowset',
			
			
			'core.search_mysql_keywords_main_query_before'			=> 'search_mysql_keywords_main_query_before',
			'core.search_native_keywords_count_query_before'		=> 'search_native_keywords_count_query_before',
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
	public function __construct(\phpbb\auth\auth $auth, \phpbb\content_visibility $content_visibility, \phpbb\db\driver\driver_interface $db, \phpbb\template\template $template, \phpbb\user $user, $forums_table, $topics_table, $posts_table)
	{
		$this->auth = $auth;
		$this->phpbb_content_visibility = $content_visibility;
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
	
	
	public function phpbb_get_logs_main_query_before($event){
		
		$permissionResult = NULL;
		$fullAccessForumIDs = array();
		
		if($event['log_type'] == LOG_MOD){
			if ($event['topic_id']){
				$permissionResult = $this->permissionEvaluate(array(
					'topic_id' => $event['topic_id'],
				));
				if($permissionResult === true){
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
				if($this->auth->acl_get('f_read_others_topics_brunoais', $forum_id)){
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
	
	public function phpbb_mcp_global_f_read_auth_after($event){
		if($event['forum_id'] && $event['topic_id']){
			$permissionResult = $this->permissionEvaluate(array(
				'forum_id' => $event['forum_id'],
				'topic_id' => $event['topic_id'],
			));
			
			if($permissionResult === 'NO_READ_OTHER'){
				trigger_error('NOT_AUTHORISED');
			}
		}
	}
	
	public function phpbb_mcp_reports_get_reports_query_before($event){
		
		$forum_ids = $event['forum_list'];
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
		
		$event['sql'] = 
		preg_replace(
				'%ORDER BY ' . preg_quote($event['sort_order_sql'], '%') . '%',
			' AND (' . $this->db->sql_in_set('t.forum_id', $fullAccessForumIDs, false, true) . '
			OR t.topic_poster = ' . (int) $this->user->data['user_id'] . ' )
			ORDER BY ' . $event['sort_order_sql'] ,
			$event['sql'],
			1
		);
	}
	
	
	public function phpbb_mcp_sorting_query_before($event){
			$fullAccessForumIDs = ($event['forum_id']) ? array($event['forum_id']) : array_intersect(get_forum_list('f_read_others_topics_brunoais'), get_forum_list('m_approve'));
			
			switch($event['mode']){
				
				case 'forum_view':
				
					$event['sql'] .= ' AND (' . $this->db->sql_in_set('forum_id', $fullAccessForumIDs, false, true) . '
					OR topic_poster = ' . (int) $this->user->data['user_id'] . ' ) ';
					
				break;
			}
			
			if($event['where_sql'] === 'WHERE'){
				$event['where_sql'] = 'WHERE ';
			}
			
	}
	
	public function phpbb_mcp_front_queue_unapproved_total_before($event){
		
		$forum_ids = $event['forum_list'];
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
		
		
		$from_sql = $event['sql_ary']['FROM'];
		$where_sql = $event['sql_ary']['WHERE'];
		
		if(!isset($from_sql[$this->topics_table])){
			$from_sql[$this->topics_table] = 't';
			$where_sql = 't.topic_id = p.topic_id
			AND '. $where_sql;
		}
		$where_sql = '(' . $this->db->sql_in_set('t.forum_id', $fullAccessForumIDs, false, true) . '
		OR t.topic_poster = ' . (int) $this->user->data['user_id'] . ' ) AND '
		. $where_sql;
		
		
		$varHold = $event['sql_ary'];
		$varHold['FROM'] = $from_sql;
		$varHold['WHERE'] = $where_sql;
		$event['sql_ary'] = $varHold;
		
	}
	
	public function phpbb_mcp_front_view_queue_postid_list_after($event){
		
		if($event['total'] > 0){	
			$forum_ids = $event['forum_list'];
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
			
			// This query needs to be re-done
			
			$sql = 'SELECT post_id
					FROM 	' . $this->posts_table . ' AS p
					INNER JOIN ' . $this->topics_table . ' AS t ON
						p.topic_id = t.topic_id
					WHERE 
						' . $this->db->sql_in_set('p.forum_id', $event['forum_list']) . '
						AND (' . $this->db->sql_in_set('p.forum_id', $fullAccessForumIDs, false, true) . '
							OR t.topic_poster = ' . (int) $this->user->data['user_id'] . '
						)
						AND ' . $this->db->sql_in_set('post_visibility', array(ITEM_UNAPPROVED, ITEM_REAPPROVE)) . '
					ORDER BY post_time DESC, post_id DESC';
				$result = $this->db->sql_query_limit($sql, 5);

				while ($row = $this->db->sql_fetchrow($result))
				{
					$post_list[] = $row['post_id'];
				}
				$this->db->sql_freeresult($result);
				
				$event['post_list'] = $post_list;
		}
	}
	
	public function phpbb_mcp_front_reports_count_query_before($event){
		$forum_ids = $event['forum_list'];
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
		
		$sql = $event['sql'];
		
		$splitedSQL = explode('FROM', $sql, 2);
		$splitedSQL[1] = ' ' . $this->topics_table . ' t,' . $splitedSQL[1];
		$sql = implode('FROM', $splitedSQL);
		
		$sql .='
					AND p.topic_id = t.topic_id
					AND (' . $this->db->sql_in_set('p.forum_id', $fullAccessForumIDs) . '
					OR t.topic_poster = ' . (int) $this->user->data['user_id'] . '
		) ';
		
		$event['sql'] = $sql;
		
	}
	
	public function phpbb_mcp_front_reports_listing_query_before($event){
		
		$forum_ids = $event['forum_list'];
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
		
		$sql_ary = $event['sql_ary'];
		
		$sql_ary['WHERE'] .= '
					AND (' . $this->db->sql_in_set('p.forum_id', $fullAccessForumIDs) . '
						OR t.topic_poster = ' . (int) $this->user->data['user_id'] . '
					)';
		
		$event['sql_ary'] = $sql_ary;
		
	}
	
	public function phpbb_content_visibility_get_visibility_sql_before($event){
		
		if(!$this->auth->acl_get('f_read_others_topics_brunoais', $event['forum_id'])){
			if($event['mode'] === 'topic'){
				$event['where_sql'] .= ' (' . $event['table_alias'] . 'topic_poster = ' . (int) $this->user->data['user_id'] . '
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
	
	public function phpbb_display_forums_after($event){
		
		$active_forum_ary = $event['active_forum_ary'];
		
		if(empty($active_forum_ary['exclude_forum_id'])){
			$forum_ids = $active_forum_ary['forum_id'];
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
			$this->accessFailed();
		}
		
		// If all checkout, I already did the f_read check and it passed, no need to do it again.
		$event['overrides_f_read_check'] = $permissionResult === true;
		
	}
	
	public function search_modify_rowset($event){
		
		// var_dump($event['rowset']);
		
		$rowset = $event['rowset'];
		
		foreach($rowset AS $key => $row){
			$permissionResult = $this->permissionEvaluate(array(
				'forum_id' => $row['forum_id'],
				'topic_poster' => $row['topic_poster'],
				'topic_type' => $row['topic_type'],
			));
			
			if($permissionResult === 'NO_READ_OTHER'){
				unset($rowset[$key]);
			}
			
		}
		
		$event['rowset'] = $rowset;
		
		
	public function search_mysql_keywords_main_query_before($event){
		$topic_id = $event['topic_id'];

		if(empty($topic_id)){

			$forums_permissions = $this->auth->acl_get_list($this->user->data['user_id'], array('f_read', 'f_read_others_topics_brunoais'));

			$ex_fid_keys = array_keys($event['ex_fid_ary']);

			$partial_read_access_fids = $full_read_access_fids = array();

			foreach($forums_permissions as $forum_id => $forum_permissions){

				if(isset($forum_permissions['f_read']) &&
					!isset($ex_fid_keys[$forum_id])){
					if(isset($forum_permissions['f_read_others_topics_brunoais'])){
						$full_read_access_fids[$forum_id] = $forum_id;
					}else{
						$partial_read_access_fids[$forum_id] = $forum_id;
					}
				}
			}

			if(sizeof($partial_read_access_fids) > 0){
				// The filter has to be in place
				$event['join_topic'] = true;

				$sql_match_where = $event['sql_match_where'];

				$sql_match_where .= ' AND (' . $this->db->sql_in_set('t.forum_id', $full_read_access_fids, false, true) . '
					OR t.topic_poster = ' . (int) $this->user->data['user_id'] . ' )';

				$event['sql_match_where'] = $sql_match_where;

			}


		}else{
			$permissionResult = $this->permissionEvaluate(array(
				'topic_id' => $topic_id,
			));

			if($permissionResult === 'NO_READ_OTHER'){
				$event['join_topic'] = true;

				$sql_match_where = $event['sql_match_where'];

				$sql_match_where .= ' AND t.topic_poster = ' . (int) $this->user->data['user_id'];

				$event['sql_match_where'] = $sql_match_where;
			}
		}
	}

	public function search_native_keywords_count_query_before($event){
		$topic_id = $event['topic_id'];

		if(empty($topic_id)){

			$forums_permissions = $this->auth->acl_get_list($this->user->data['user_id'], array('f_read', 'f_read_others_topics_brunoais'));

			$ex_fid_keys = array_keys($event['ex_fid_ary']);

			$partial_read_access_fids = $full_read_access_fids = array();

			foreach($forums_permissions as $forum_id => $forum_permissions){

				if(isset($forum_permissions['f_read']) &&
					!isset($ex_fid_keys[$forum_id])){
					if(isset($forum_permissions['f_read_others_topics_brunoais'])){
						$full_read_access_fids[$forum_id] = $forum_id;
					}else{
						$partial_read_access_fids[$forum_id] = $forum_id;
					}
				}
			}

			if(sizeof($partial_read_access_fids) > 0){
				// The filter has to be in place
				$event['total_results'] = false;
				$event['left_join_topics'] = true;

				$sql_where = $event['sql_where'];

				$sql_where[] = '(' . $this->db->sql_in_set('t.forum_id', $full_read_access_fids, false, true) . '
					OR t.topic_poster = ' . (int) $this->user->data['user_id'] . ' )';

				$event['sql_where'] = $sql_where;

			}


		}else{
			$permissionResult = $this->permissionEvaluate(array(
				'topic_id' => $topic_id,
			));

			if($permissionResult === 'NO_READ_OTHER'){
				$event['total_results'] = -1;
				$event['left_join_topics'] = true;

				$sql_where = $event['sql_where'];

				$sql_where[] = 't.topic_poster = ' . (int) $this->user->data['user_id'];

				$event['sql_where'] = $sql_where;
			}
		}
	}

	//
	// Auxiliary functions
	//

	private function accessFailed(){
		$this->user->add_lang_ext('brunoais/readOthersTopics', 'common');
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
		if(!isset($info['forum_id']) || $info['forum_id'] == 0){
			if(isset($info['topic_id']) && $info['topic_id'] != 0){
				$this->getForumIdAndPosterFromTopic($info);
			}else if(!isset($info['post_id']) || $info['post_id'] == 0){
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
