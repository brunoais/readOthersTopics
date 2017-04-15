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
class search_listener implements EventSubscriberInterface
{
	static public function getSubscribedEvents()
	{
		return array(
			'core.search_modify_rowset'								=> 'search_modify_rowset',

			'core.search_mysql_keywords_main_query_before'			=> 'search_mysql_keywords_main_query_before',
			'core.search_native_keywords_count_query_before'		=> 'search_native_keywords_count_query_before',
			'core.search_postgres_keywords_main_query_before'		=> 'search_postgres_keywords_main_query_before',

			'core.search_mysql_by_keyword_modify_search_key'		=> 'search_mysql_by_keyword_modify_search_key',
			'core.search_native_by_keyword_modify_search_key'		=> 'search_native_by_keyword_modify_search_key',
			'core.search_postgres_by_keyword_modify_search_key'		=> 'search_postgres_by_keyword_modify_search_key',

			'core.search_mysql_author_query_before'					=> 'search_mysql_author_query_before',
			'core.search_native_author_count_query_before'			=> 'search_native_author_count_query_before',
			'core.search_postgres_author_count_query_before'		=> 'search_postgres_author_count_query_before',

			'core.search_mysql_by_author_modify_search_key'			=> 'search_mysql_by_author_modify_search_key',
			'core.search_native_by_author_modify_search_key'		=> 'search_native_by_author_modify_search_key',
			'core.search_postgres_by_author_modify_search_key'		=> 'search_postgres_by_author_modify_search_key',

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
	*
	* @param	\phpbb\auth\auth					$auth	Auth object
	* @param	\phpbb\user							$user	User object
	* @param	\phpbb\db\driver\driver_interface	$db		Database object
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

	public function search_modify_rowset($event){

		$rowset = $event['rowset'];

		foreach($rowset AS $key => $row){
			$permissionResult = $this->permission_evaluation->permissionEvaluate(array(
				'forum_id' => $row['forum_id'],
				'topic_poster' => $row['topic_poster'],
				'topic_type' => $row['topic_type'],
			));

			$accesses = $this->accesses;
			if($permissionResult === $accesses::NO_READ_OTHER){
				unset($rowset[$key]);
			}
		}
		$event['rowset'] = $rowset;
	}

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
			$permissionResult = $this->permission_evaluation->permissionEvaluate(array(
				'topic_id' => $topic_id,
			));

			$accesses = $this->accesses;
			if($permissionResult === $accesses::NO_READ_OTHER){
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
			$permissionResult = $this->permission_evaluation->permissionEvaluate(array(
				'topic_id' => $topic_id,
			));

			$accesses = $this->accesses;
			if($permissionResult === $accesses::NO_READ_OTHER){
				$event['total_results'] = -1;
				$event['left_join_topics'] = true;

				$sql_where = $event['sql_where'];

				$sql_where[] = 't.topic_poster = ' . (int) $this->user->data['user_id'];

				$event['sql_where'] = $sql_where;
			}
		}
	}

	public function search_postgres_keywords_main_query_before($event){
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
			$permissionResult = $this->permission_evaluation->permissionEvaluate(array(
				'topic_id' => $topic_id,
			));

			$accesses = $this->accesses;
			if($permissionResult === $accesses::NO_READ_OTHER){
				$event['join_topic'] = true;

				$sql_match_where = $event['sql_match_where'];

				$sql_match_where .= ' AND t.topic_poster = ' . (int) $this->user->data['user_id'];

				$event['sql_match_where'] = $sql_match_where;
			}
		}
	}
	
	
	private function search_cache_check($search_key_array, $topic_id, $excluding_forums){
		
		if($topic_id > 0){
			$permissionResult = $this->permission_evaluation->permissionEvaluate(array(
				'topic_id' => $topic_id,
			));
			$accesses = $this->accesses;
			if($permissionResult === $accesses::NO_READ_OTHER){
				return array('NoAccessResult'); 
			}
		} else {
			$forums_permissions = $this->auth->acl_get_list($this->user->data['user_id'], array('f_read', 'f_read_others_topics_brunoais'));
			$ex_fid_keys = array_keys($excluding_forums);
			$partial_read_access_fids = $full_read_access_fids = array();

			foreach($forums_permissions as $forum_id => $forum_permissions){
				if(isset($forum_permissions['f_read']) &&
					!isset($ex_fid_keys[$forum_id])){
					if(!isset($forum_permissions['f_read_others_topics_brunoais'])){
						$search_key_array[] = (int) $this->user->data['user_id']; 
						return $search_key_array;
					}
				}
			}
		}
		return $search_key_array;
	}
	
	public function search_mysql_by_keyword_modify_search_key($event){
		$event['search_key_array'] = $this->search_cache_check($event['search_key_array'], $event['topic_id'], $event['ex_fid_ary']);
	}
	
	public function search_native_by_keyword_modify_search_key($event){
		$event['search_key_array'] = $this->search_cache_check($event['search_key_array'], $event['topic_id'], $event['ex_fid_ary']);
	}
	
	public function search_postgres_by_keyword_modify_search_key($event){
		$event['search_key_array'] = $this->search_cache_check($event['search_key_array'], $event['topic_id'], $event['ex_fid_ary']);
	}
	
	public function search_mysql_by_author_modify_search_key($event){
		$event['search_key_array'] = $this->search_cache_check($event['search_key_array'], $event['topic_id'], $event['ex_fid_ary']);
	}
	
	public function search_native_by_author_modify_search_key($event){
		$event['search_key_array'] = $this->search_cache_check($event['search_key_array'], $event['topic_id'], $event['ex_fid_ary']);
	}
	
	public function search_postgres_by_author_modify_search_key($event){
		$event['search_key_array'] = $this->search_cache_check($event['search_key_array'], $event['topic_id'], $event['ex_fid_ary']);
	}
	

	public function search_mysql_author_query_before($event){

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

				// Workaround for a mistake I made myself when making the event.
				$sql_sort_join = $event['sql_sort_join'];
				$sql_sort_join .= ' AND t_brunoais.topic_id = p.topic_id ';
				$event['sql_sort_join'] = $sql_sort_join;

				$sql_sort_table = $event['sql_sort_table'];
				$sql_sort_table .= TOPICS_TABLE . ' t_brunoais, ';
				$event['sql_sort_table'] = $sql_sort_table;


				$sql_fora = $event['sql_fora'];

				$sql_fora .= ' AND (' . $this->db->sql_in_set('t_brunoais.forum_id', $full_read_access_fids, false, true) . '
					OR t_brunoais.topic_poster = ' . (int) $this->user->data['user_id'] . ' )';

				$event['sql_fora'] = $sql_fora;

			}


		}else{
			$permissionResult = $this->permission_evaluation->permissionEvaluate(array(
				'topic_id' => $topic_id,
			));

			$accesses = $this->accesses;
			if($permissionResult === $accesses::NO_READ_OTHER){

				// Workaround for a mistake I made myself when making the event.
				$sql_sort_join = $event['sql_sort_join'];
				$sql_sort_join .= ' AND t_brunoais.topic_id = p.topic_id ';
				$event['sql_sort_join'] = $sql_sort_join;

				$sql_sort_table = $event['sql_sort_table'];
				$sql_sort_table .= TOPICS_TABLE . ' t_brunoais, ';
				$event['sql_sort_table'] = $sql_sort_table;


				$sql_fora = $event['sql_fora'];

				$sql_fora .= ' AND t.topic_poster = ' . (int) $this->user->data['user_id'];

				$event['sql_fora'] = $sql_fora;
			}
		}
	}

	public function search_native_author_count_query_before($event){

		$topic_id = $event['topic_id'];

		if(empty($topic_id)){

			$forums_permissions = $this->auth->acl_get_list($this->user->data['user_id'], array('f_read', 'f_read_others_topics_brunoais'));

			$ex_fid_ary = $event['ex_fid_ary'];
			
			$ex_fid_keys = array_flip($ex_fid_ary);

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

				if ($event['type'] == 'posts' && !$event['firstpost_only']){
					$event['firstpost_only'] = true;
					$event['sql_firstpost'] = ' AND p.topic_id = t.topic_id';
				}
				
				$sql_sort_table = $event['sql_sort_table'];
				$sql_sort_table .= TOPICS_TABLE . ' t_brunoais, ';
				$event['sql_sort_table'] = $sql_sort_table;


				$sql_fora = $event['sql_fora'];
				$sql_fora .= ' AND t_brunoais.topic_id = p.topic_id ';
				$sql_fora .= ' AND (' . $this->db->sql_in_set('t_brunoais.forum_id', $full_read_access_fids, false, true) . '
					OR t_brunoais.topic_poster = ' . (int) $this->user->data['user_id'] . ' )';

				$event['sql_fora'] = $sql_fora;
			}


		}else{
			$permissionResult = $this->permission_evaluation->permissionEvaluate(array(
				'topic_id' => $topic_id,
			));

			$accesses = $this->accesses;
			if($permissionResult === $accesses::NO_READ_OTHER){

				// Workaround for a mistake I made myself when making the event.
				$sql_sort_join = $event['sql_sort_join'];
				$sql_sort_join .= ' AND t_brunoais.topic_id = p.topic_id ';
				$event['sql_sort_join'] = $sql_sort_join;

				$sql_sort_table = $event['sql_sort_table'];
				$sql_sort_table .= TOPICS_TABLE . ' t_brunoais, ';
				$event['sql_sort_table'] = $sql_sort_table;


				$sql_fora = $event['sql_fora'];

				$sql_fora .= ' AND t_brunoais.topic_poster = ' . (int) $this->user->data['user_id'];

				$event['sql_fora'] = $sql_fora;
				
			}
		}

	}


	public function search_postgres_author_count_query_before($event){

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

				// Workaround for a mistake I made myself when making the event.
				$sql_sort_join = $event['sql_sort_join'];
				$sql_sort_join .= ' AND t_brunoais.topic_id = p.topic_id ';
				$event['sql_sort_join'] = $sql_sort_join;

				$sql_sort_table = $event['sql_sort_table'];
				$sql_sort_table .= TOPICS_TABLE . ' t_brunoais, ';
				$event['sql_sort_table'] = $sql_sort_table;


				$sql_fora = $event['sql_fora'];

				$sql_fora .= ' AND (' . $this->db->sql_in_set('t_brunoais.forum_id', $full_read_access_fids, false, true) . '
					OR t_brunoais.topic_poster = ' . (int) $this->user->data['user_id'] . ' )';

				$event['sql_fora'] = $sql_fora;

			}


		}else{
			$permissionResult = $this->permission_evaluation->permissionEvaluate(array(
				'topic_id' => $topic_id,
			));

			$accesses = $this->accesses;
			if($permissionResult === $accesses::NO_READ_OTHER){

				// Workaround for a mistake I made myself when making the event.
				$sql_sort_join = $event['sql_sort_join'];
				$sql_sort_join .= ' AND t_brunoais.topic_id = p.topic_id ';
				$event['sql_sort_join'] = $sql_sort_join;

				$sql_sort_table = $event['sql_sort_table'];
				$sql_sort_table .= TOPICS_TABLE . ' t_brunoais, ';
				$event['sql_sort_table'] = $sql_sort_table;


				$sql_fora = $event['sql_fora'];

				$sql_fora .= ' AND t_brunoais.topic_poster = ' . (int) $this->user->data['user_id'];

				$event['sql_fora'] = $sql_fora;
			}
		}

	}
}

