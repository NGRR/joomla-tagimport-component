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
use Joomla\CMS\User\User;

/**
 * Category Import helper class
 *
 * @since  1.0.0
 */
class CategoryImportHelper
{
	/**
	 * Configure the toolbar for the component
	 *
	 * @param   string  $view  The current view
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */	public static function configureToolbar($view)
	{
		$user = Factory::getUser();
		$app = Factory::getApplication();

		// Add title
		$app->getDocument()->setTitle(\Joomla\CMS\Language\Text::_('COM_CATEGORYIMPORT'));

		// Common permissions check
		$canDo = self::getActions();

		if ($canDo->get('core.admin')) {
			\Joomla\CMS\Toolbar\ToolbarHelper::preferences('com_categoryimport');
		}
	}

	/**
	 * Get the ACL actions for this component
	 *
	 * @param   string  $assetName  The asset name
	 *
	 * @return  \Joomla\CMS\Object\CMSObject  Object containing the allowed actions
	 *
	 * @since   1.0.0
	 */
	public static function getActions($assetName = 'com_categoryimport')
	{
		$user = Factory::getUser();
		$result = new \Joomla\CMS\Object\CMSObject();

		$actions = [
			'core.admin',
			'core.options',
			'core.manage',
			'core.create',
			'core.delete',
			'core.edit',
			'core.edit.state',
			'core.edit.own',
			'categoryimport.import',
			'categoryimport.batch',
			'categoryimport.export'
		];

		foreach ($actions as $action) {
			$result->set($action, $user->authorise($action, $assetName));
		}

		return $result;
	}

	/**
	 * Check if the current user can import categories
	 *
	 * @param   User    $user       The user object (optional, defaults to current user)
	 * @param   string  $assetName  The asset name
	 *
	 * @return  boolean  True if the user can import, false otherwise
	 *
	 * @since   1.0.0
	 */
	public static function canImport($user = null, $assetName = 'com_categoryimport')
	{
		$user = $user ?: Factory::getUser();
		return $user->authorise('categoryimport.import', $assetName);
	}

	/**
	 * Check if the current user can perform batch operations
	 *
	 * @param   User    $user       The user object (optional, defaults to current user)
	 * @param   string  $assetName  The asset name
	 *
	 * @return  boolean  True if the user can perform batch operations, false otherwise
	 *
	 * @since   1.0.0
	 */
	public static function canBatch($user = null, $assetName = 'com_categoryimport')
	{
		$user = $user ?: Factory::getUser();
		return $user->authorise('categoryimport.batch', $assetName);
	}

	/**
	 * Check if the current user can export categories
	 *
	 * @param   User    $user       The user object (optional, defaults to current user)
	 * @param   string  $assetName  The asset name
	 *
	 * @return  boolean  True if the user can export, false otherwise
	 *
	 * @since   1.0.0
	 */
	public static function canExport($user = null, $assetName = 'com_categoryimport')
	{
		$user = $user ?: Factory::getUser();
		return $user->authorise('categoryimport.export', $assetName);
	}

	/**
	 * Check if the current user can manage the component
	 *
	 * @param   User    $user       The user object (optional, defaults to current user)
	 * @param   string  $assetName  The asset name
	 *
	 * @return  boolean  True if the user can manage, false otherwise
	 *
	 * @since   1.0.0
	 */
	public static function canManage($user = null, $assetName = 'com_categoryimport')
	{
		$user = $user ?: Factory::getUser();
		return $user->authorise('core.manage', $assetName);
	}
}
