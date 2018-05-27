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
class acp_listener implements EventSubscriberInterface
{
	static public function getSubscribedEvents()
	{
		return array(
			'core.acp_manage_forums_request_data'	=> 'phpbb_acp_manage_forums_request_data',
		);
	}

	/* @var \phpbb\request\request */
	protected $request;

	/**
	* Constructor
	*/
	public function __construct(\phpbb\request\request $request)
	{
		$this->request = $request;
	}

	public function phpbb_acp_manage_forums_request_data($event)
	{
		$forum_data = $event['forum_data'];
		$forum_data['brunoais_read_other_true_last_accessible'] = $this->request->variable('brunoais_read_other_true_last_accessible', false);
		$event['forum_data'] = $forum_data;
	}

}

