<?php
/**
*
* @package phpBB Extension - brunoais readOthersTopics
* @copyright (c) 2015 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace brunoais\readOthersTopics;

/**
* Extension class for custom enable/disable/purge actions
*
* NOTE TO EXTENSION DEVELOPERS:
* Normally it is not necessary to define any functions inside the ext class below.
* The ext class may contain special (un)installation commands in the methods
* enable_step(), disable_step() and purge_step(). As it is, these methods are defined
* in phpbb_extension_base, which this class extends, but you can overwrite them to
* give special instructions for those cases. Board Rules must do this because it uses
* the notifications system, which requires the methods enable_notifications(),
* disable_notifications() and purge_notifications() be run to properly manage the
* notifications created by Board Rules when enabling, disabling or deleting this
* extension.
*/
class ext extends \phpbb\extension\base
{

}
