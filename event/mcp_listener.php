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

use brunoais\readOthersTopics\shared\accesses;

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

	protected $info_storage;

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
	
	
	/* Tables */
	public $topics_table;

	/**
	* Constructor
	*
	* @param	\phpbb\auth\auth					$auth	Auth object
	* @param	\phpbb\user							$user	User object
	* @param	\phpbb\db\driver\driver_interface	$db		Database object
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\content_visibility $content_visibility, \phpbb\db\driver\driver_interface $db, \phpbb\template\template $template, \phpbb\user $user, \brunoais\readOthersTopics\shared\permission_evaluation $permission_evaluation, 
	$topics_table)
	{
		$this->auth = $auth;
		$this->phpbb_content_visibility = $content_visibility;
		$this->db = $db;
		$this->template = $template;
		$this->user = $user;
		$this->permission_evaluation = $permission_evaluation;
		$this->topics_table = $topics_table;

		$this->info_storage = array();
	}

	public function phpbb_mcp_global_f_read_auth_after($event)
	{
		if ($event['forum_id'] && $event['topic_id'])
		{
			$permission_result = $this->permission_evaluation->permission_evaluate(array(
				'forum_id' => $event['forum_id'],
				'topic_id' => $event['topic_id'],
			));

			if ($permission_result === accesses::NO_READ_OTHER)
			{
				trigger_error('NOT_AUTHORISED');
			}
		}
	}

	public function phpbb_mcp_reports_get_reports_query_before($event)
	{

		$forum_ids = $event['forum_list'];
		$full_access_forum_IDs = array();
		foreach ($forum_ids AS $forum_id)
		{
			if ($this->auth->acl_get('f_read_others_topics_brunoais', $forum_id))
			{
					$full_access_forum_IDs[] = $forum_id;
			}
		}

		if (sizeof($full_access_forum_IDs) === sizeof($forum_ids))
		{
			// Nothing to filter
			return;
		}

		$event['sql'] =
		preg_replace(
				'%ORDER BY ' . preg_quote($event['sort_order_sql'], '%') . '%',
			' AND (' . $this->db->sql_in_set('t.forum_id', $full_access_forum_IDs, false, true) . '
			OR t.topic_poster = ' . (int) $this->user->data['user_id'] . ' )
			ORDER BY ' . $event['sort_order_sql'] ,
			$event['sql'],
			1
		);
	}


	public function phpbb_mcp_sorting_query_before($event)
	{
			$full_access_forum_IDs = ($event['forum_id']) ? array($event['forum_id']) : array_intersect(get_forum_list('f_read_others_topics_brunoais'), get_forum_list('m_approve'));

			switch($event['mode'])
			{

				case 'forum_view':
					$event['sql'] .= ' AND (' . $this->db->sql_in_set('forum_id', $full_access_forum_IDs, false, true) . '
					OR topic_poster = ' . (int) $this->user->data['user_id'] . ' ) ';

				break;
			}

			if ($event['where_sql'] === 'WHERE')
			{
				$event['where_sql'] = 'WHERE ';
			}

	}

	public function phpbb_mcp_front_queue_unapproved_total_before($event)
	{

		$forum_ids = $event['forum_list'];
		$full_access_forum_IDs = array();
		foreach ($forum_ids AS $forum_id)
		{
			if ($this->auth->acl_get('f_read_others_topics_brunoais', $forum_id))
			{
					$full_access_forum_IDs[] = $forum_id;
			}
		}

		if (sizeof($full_access_forum_IDs) === sizeof($forum_ids))
		{
			// Nothing to filter
			return;
		}


		$from_sql = $event['sql_ary']['FROM'];
		$where_sql = $event['sql_ary']['WHERE'];

		if (!isset($from_sql[$this->topics_table]))
		{
			$from_sql[$this->topics_table] = 't';
			$where_sql = 't.topic_id = p.topic_id
			AND '. $where_sql;
		}
		$where_sql = '(' . $this->db->sql_in_set('t.forum_id', $full_access_forum_IDs, false, true) . '
		OR t.topic_poster = ' . (int) $this->user->data['user_id'] . ' ) AND '
		. $where_sql;


		$var_hold = $event['sql_ary'];
		$var_hold['FROM'] = $from_sql;
		$var_hold['WHERE'] = $where_sql;
		$event['sql_ary'] = $var_hold;

	}

	public function phpbb_mcp_front_view_queue_postid_list_after($event)
	{

		if ($event['total'] > 0)
		{
			$forum_ids = $event['forum_list'];
			$full_access_forum_IDs = array();
			foreach ($forum_ids AS $forum_id)
			{
				if ($this->auth->acl_get('f_read_others_topics_brunoais', $forum_id))
				{
					$full_access_forum_IDs[] = $forum_id;
				}
			}

			if (sizeof($full_access_forum_IDs) === sizeof($forum_ids))
			{
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
						' . $this->db->sql_in_set('p.forum_id', $event['forum_list'], false, true) . '
						AND (' . $this->db->sql_in_set('p.forum_id', $full_access_forum_IDs, false, true) . '
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
			$event['total'] = count($post_list);
		}
	}

	public function phpbb_mcp_front_reports_count_query_before($event)
	{
		$forum_ids = $event['forum_list'];
		$full_access_forum_IDs = array();
		foreach ($forum_ids AS $forum_id)
		{
			if ($this->auth->acl_get('f_read_others_topics_brunoais', $forum_id))
			{
				$full_access_forum_IDs[] = $forum_id;
			}
		}

		if (sizeof($full_access_forum_IDs) === sizeof($forum_ids))
		{
			// Nothing to filter
			return;
		}

		$sql = $event['sql'];

		$splited_sql = explode('FROM', $sql, 2);
		$splited_sql[1] = ' ' . $this->topics_table . ' t,' . $splited_sql[1];
		$sql = implode('FROM', $splited_sql);

		$sql .='
					AND p.topic_id = t.topic_id
					AND (' . $this->db->sql_in_set('p.forum_id', $full_access_forum_IDs, true) . '
					OR t.topic_poster = ' . (int) $this->user->data['user_id'] . '
		) ';

		$event['sql'] = $sql;

	}

	public function phpbb_mcp_front_reports_listing_query_before($event)
	{

		$forum_ids = $event['forum_list'];
		$full_access_forum_IDs = array();
		foreach ($forum_ids AS $forum_id)
		{
			if ($this->auth->acl_get('f_read_others_topics_brunoais', $forum_id))
			{
				$full_access_forum_IDs[] = $forum_id;
			}
		}

		if (sizeof($full_access_forum_IDs) === sizeof($forum_ids))
		{
			// Nothing to filter
			return;
		}

		$sql_ary = $event['sql_ary'];

		$sql_ary['WHERE'] .= '
					AND (' . $this->db->sql_in_set('p.forum_id', $full_access_forum_IDs) . '
						OR t.topic_poster = ' . (int) $this->user->data['user_id'] . '
					)';

		$event['sql_ary'] = $sql_ary;

	}
}

