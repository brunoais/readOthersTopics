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
			'core.user_setup'						=> 'load_language_on_setup',
		);
	}

	protected $infoStorage;
	
	/* @var \phpbb\auth\auth */
	protected $auth;
	
	/* @var \phpbb\user */
	protected $user;
	
	/* @var \phpbb\db\driver\driver_interface */
	protected $db;
	
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
	public function __construct(\phpbb\auth\auth $auth, \phpbb\db\driver\driver_interface $db, \phpbb\user $user, $forums_table, $topics_table, $posts_table)
	{
		$this->auth = $auth;
		$this->db = $db;
		$this->user = $user;
		$this->forums_table = $forums_table;
		$this->topics_table = $topics_table;
		$this->posts_table = $posts_table;
		
		$this->infoStorage = array();
	}
	}

	public function load_language_on_setup($event)
	{
		
	}

}
