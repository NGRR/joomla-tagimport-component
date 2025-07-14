<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_categoryimport
 *
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Adapter\ComponentAdapter;

/**
 * Installation class to perform additional setup tasks
 *
 * @since  1.0.0
 */
class Com_CategoryImportInstallerScript
{
	/**
	 * Method to install the extension
	 *
	 * @param   ComponentAdapter  $parent  The class calling this method
	 *
	 * @return  boolean  True on success
	 *
	 * @since   1.0.0
	 */
	public function install(ComponentAdapter $parent)
	{
		// Load component language file
		$lang = Factory::getLanguage();
		$lang->load('com_categoryimport', JPATH_ADMINISTRATOR, null, false, true);
		$lang->load('com_categoryimport.sys', JPATH_ADMINISTRATOR, null, false, true);
		
		Factory::getApplication()->enqueueMessage('Category Import Component installed successfully!', 'success');
		return true;
	}

	/**
	 * Method to update the extension
	 *
	 * @param   ComponentAdapter  $parent  The class calling this method
	 *
	 * @return  boolean  True on success
	 *
	 * @since   1.0.0
	 */
	public function update(ComponentAdapter $parent)
	{
		// Load component language file
		$lang = Factory::getLanguage();
		$lang->load('com_categoryimport', JPATH_ADMINISTRATOR, null, false, true);
		$lang->load('com_categoryimport.sys', JPATH_ADMINISTRATOR, null, false, true);
		
		Factory::getApplication()->enqueueMessage('Category Import Component updated successfully!', 'success');
		return true;
	}

	/**
	 * Method to run after an install/update/uninstall method
	 *
	 * @param   string            $type    The action being performed
	 * @param   ComponentAdapter  $parent  The class calling this method
	 *
	 * @return  boolean  True on success
	 *
	 * @since   1.0.0
	 */
	public function postflight($type, ComponentAdapter $parent)
	{
		if ($type === 'install' || $type === 'update')
		{
			Factory::getApplication()->enqueueMessage('Component ready to use. Please check System > Permissions to configure access.', 'info');
		}

		return true;
	}
}
