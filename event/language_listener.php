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
	
	public static function insertInAssocArray($original, $searchingKey, $newThingKey, $newThing){
		
		$rebuild = array();
		
		foreach($original AS $key => $elem){
			$rebuild[$key] = $elem;
			if($key == $searchingKey){
				$rebuild[$newThingKey] = $newThing;
			}
		}
		
		return $rebuild;
		
	}

	/**
	* Constructor
	*
	*/
	public function __construct()
	{
	}

	public function adapt_permission_logic($event)
	{
		
		$event['permissions'] = self::insertInAssocArray($event['permissions'],
				\brunoais\readOthersTopics\migrations\initial_release::PARENT_PERMISSION_NAME,
				\brunoais\readOthersTopics\migrations\initial_release::PERMISSION_NAME,
				array(
					'lang'	=> 'ACL_F_READ_OTHERS_TOPICS',
					'cat'	=> 'actions',
				)
			);
		
		return $event;
		
	}
}
