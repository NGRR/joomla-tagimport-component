<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_tagimport
 *
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace TagImport\Component\TagImport\Administrator\Extension;

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\User\User;
use Psr\Container\ContainerInterface;

/**
 * Component class for com_tagimport
 *
 * @since  1.0.0
 */
class TagImportComponent extends MVCComponent implements BootableExtensionInterface
{
	use HTMLRegistryAwareTrait;

	/**
	 * Booting the extension. This is the function to set up the environment of the extension like
	 * registering new class loaders, etc.
	 *
	 * If required, some initial set up can be done from services of the container, eg.
	 * registering HTML services.
	 *	 * @param   ContainerInterface  $container  The container
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */	public function boot(ContainerInterface $container)
	{
		// Add logging for debugging
		\Joomla\CMS\Log\Log::addLogger(
			['text_file' => 'tagimport_boot.php'],
			\Joomla\CMS\Log\Log::ALL,
			['tagimport.boot']
		);
		
		\Joomla\CMS\Log\Log::add('TagImportComponent boot() called', \Joomla\CMS\Log\Log::INFO, 'tagimport.boot');
		
		try {
			// Set up the HTML Registry
			$this->setRegistry($container->get(\Joomla\CMS\HTML\Registry::class));
			\Joomla\CMS\Log\Log::add('HTML Registry set successfully', \Joomla\CMS\Log\Log::INFO, 'tagimport.boot');
			
			// Register the HTML service
			$this->getRegistry()->register('tagimport', new \TagImport\Component\TagImport\Administrator\Service\HTML\TagImport());
			\Joomla\CMS\Log\Log::add('HTML service registered successfully', \Joomla\CMS\Log\Log::INFO, 'tagimport.boot');
		} catch (\Exception $e) {
			\Joomla\CMS\Log\Log::add('Error in boot(): ' . $e->getMessage(), \Joomla\CMS\Log\Log::ERROR, 'tagimport.boot');
			throw $e;
		}
	}

	/**
	 * Check if the current user can perform the specified action
	 *
	 * @param   string  $action     The action to check
	 * @param   string  $assetName  The asset name (optional)
	 * @param   User    $user       The user object (optional, defaults to current user)
	 *
	 * @return  boolean  True if the user can perform the action, false otherwise
	 *
	 * @since   1.0.0
	 */
	public function canDo($action, $assetName = null, $user = null)
	{
		$user = $user ?: \Joomla\CMS\Factory::getUser();
		$assetName = $assetName ?: 'com_tagimport';

		return $user->authorise($action, $assetName);
	}

	/**
	 * Check if the current user can import tags
	 *
	 * @param   User  $user  The user object (optional, defaults to current user)
	 *
	 * @return  boolean  True if the user can import, false otherwise
	 *
	 * @since   1.0.0
	 */
	public function canImport($user = null)
	{
		return $this->canDo('tagimport.import', null, $user);
	}

	/**
	 * Check if the current user can perform batch operations
	 *
	 * @param   User  $user  The user object (optional, defaults to current user)
	 *
	 * @return  boolean  True if the user can perform batch operations, false otherwise
	 *
	 * @since   1.0.0
	 */
	public function canBatch($user = null)
	{
		return $this->canDo('tagimport.batch', null, $user);
	}

	/**
	 * Check if the current user can export tags
	 *
	 * @param   User  $user  The user object (optional, defaults to current user)
	 *
	 * @return  boolean  True if the user can export, false otherwise
	 *
	 * @since   1.0.0
	 */
	public function canExport($user = null)
	{
		return $this->canDo('tagimport.export', null, $user);
	}
}
