<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

namespace brunoais\readOthersTopics\overrides\message;

use brunoais\readOthersTopics\shared\accesses;


class topic_form extends \phpbb\message\topic_form
{
	
	/* @var \brunoais\readOthersTopics\shared\permission_evaluation */
	protected $permission_evaluation;
	
	/**
	* Constructor
	*/
	public function __construct(\brunoais\readOthersTopics\shared\permission_evaluation $permission_evaluation, ...$passthrough_args)
	{
		parent::__construct(...$passthrough_args);
		$this->permission_evaluation = $permission_evaluation;
	}

	/**
	* {inheritDoc}
	*/
	public function check_allow()
	{
		$error = parent::check_allow();
		if ($error)
		{
			return $error;
		}
		$permission_result = $this->permission_evaluation->permission_evaluate(array(
			'topic_id' => $this->topic_id,
		));
		if ($permission_result === accesses::NO_READ_OTHER)
		{
			$this->user->add_lang_ext('brunoais/readOthersTopics', 'common');
			return 'SORRY_AUTH_READ_OTHER';
		}
		return false;
	}


}
