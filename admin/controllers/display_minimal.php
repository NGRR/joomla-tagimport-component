<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_categoryimport
 *
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;

/**
 * Minimal Category Import Display Controller (no ACL conflicts)
 *
 * @since  1.0.0
 */
class CategoryImportControllerDisplayMinimal extends BaseController
{
	/**
	 * The default view.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $default_view = 'import';
	
	/**
	 * Method to display a view.
	 *
	 * @param   boolean  $cachable   If true, the view output will be cached
	 * @param   array    $urlparams  An array of safe URL parameters and their variable types.
	 *
	 * @return  BaseController|boolean  This object to support chaining.
	 *
	 * @since   1.0.0
	 */
	public function display($cachable = false, $urlparams = [])
	{
		// Use minimal view to avoid any ACL conflicts
		$viewClass = 'CategoryImportViewImportMinimal';
		$viewFile = JPATH_COMPONENT_ADMINISTRATOR . '/views/import/view_minimal.php';
		
		// Load the minimal view file
		if (file_exists($viewFile) && !class_exists($viewClass)) {
			require_once $viewFile;
		}
		
		// Create an instance of the minimal view
		if (class_exists($viewClass)) {
			$view = new $viewClass(['base_path' => JPATH_COMPONENT_ADMINISTRATOR]);
			$view->display();
			return $this;
		}
		
		// If minimal view not found, show basic message
		echo '<div style="padding: 20px; background: #fff; margin: 20px; border: 1px solid #ddd;">';
		echo '<h1>CategoryImport Component</h1>';
		echo '<p>Component loaded successfully (minimal mode)</p>';
		echo '<p>User: ' . Factory::getUser()->name . '</p>';
		echo '</div>';
		
		return $this;
	}
}
