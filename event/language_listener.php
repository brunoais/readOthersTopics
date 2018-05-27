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
class language_listener implements EventSubscriberInterface
{
	static public function getSubscribedEvents()
	{
		return array(
			'core.permissions'						=> 'adapt_permission_logic',
		);
	}
	
	public static function insert_at_in_assoc_array($original, $searching_key, $new_thing_key, $new_thing)
	{
		$rebuild = array();
		foreach ($original AS $key => $elem)
		{
			$rebuild[$key] = $elem;
			if ($key == $searching_key)
			{
				$rebuild[$new_thing_key] = $new_thing;
			}
		}
		
		return $rebuild;
	}


	public function adapt_permission_logic($event)
	{
		
		$event['permissions'] = self::insert_at_in_assoc_array($event['permissions'],
				\brunoais\readOthersTopics\migrations\initial_release::PARENT_PERMISSION_NAME,
				\brunoais\readOthersTopics\migrations\initial_release::PERMISSION_NAME,
				array(
					'lang'	=> 'ACL_F_READ_OTHERS_TOPICS',
					'cat'	=> 'actions',
				)
			);
		
	}
}
