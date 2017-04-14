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
class mcp_listener implements EventSubscriberInterface
{
	static public function getSubscribedEvents()
	{
		return array(
			'core.mcp_global_f_read_auth_after'						=> 'phpbb_mcp_global_f_read_auth_after',
			'core.mcp_reports_get_reports_query_before'				=> 'phpbb_mcp_reports_get_reports_query_before',
			'core.mcp_sorting_query_before'							=> 'phpbb_mcp_sorting_query_before',

			'core.mcp_front_queue_unapproved_total_before'			=> 'phpbb_mcp_front_queue_unapproved_total_before',
			'core.mcp_front_view_queue_postid_list_after'			=> 'phpbb_mcp_front_view_queue_postid_list_after',
			'core.mcp_front_reports_count_query_before'				=> 'phpbb_mcp_front_reports_count_query_before',
			'core.mcp_front_reports_listing_query_before'			=> 'phpbb_mcp_front_reports_listing_query_before',
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

	public function phpbb_mcp_global_f_read_auth_after($event){
		if($event['forum_id'] && $event['topic_id']){
			$permissionResult = $this->permissionEvaluate(array(
				'forum_id' => $event['forum_id'],
				'topic_id' => $event['topic_id'],
			));

			if($permissionResult === Accesses::NO_READ_OTHER){
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

			// This variable needs to exist
			$post_list = array();
			
			// This query needs to be re-done

			$sql = 'SELECT post_id
					FROM 	' . $this->posts_table . ' AS p
					INNER JOIN ' . $this->topics_table . ' AS t ON
						p.topic_id = t.topic_id
					WHERE
						' . $this->db->sql_in_set('p.forum_id', $event['forum_list'], true) . '
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
					AND (' . $this->db->sql_in_set('p.forum_id', $fullAccessForumIDs, true) . '
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


	//
	// Auxiliary functions
	//


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

	private function permissionEvaluate($info)
	{
		if(empty($info['forum_id'])){
			if(!empty($info['topic_id'])){
				$this->getForumIdAndPosterFromTopic($info);
			}else if(!empty($info['post_id'])){
				$this->getForumIdAndTopicFromPost($info);
			}
		}

		if(!$this->auth->acl_get('f_read', $info['forum_id'])){
			return Accesses::NO_READ;
		}


		if(!$this->auth->acl_get('f_read_others_topics_brunoais', $info['forum_id'])){
			if($this->user->data['user_id'] == ANONYMOUS){
				return Accesses::NO_READ_OTHER;
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
					return Accesses::NO_READ_OTHER;
				}
			}
		}

		return true;

	}

}

