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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;

/**
 * Category Import Display Controller
 *
 * @since  1.0.0
 */
class CategoryImportControllerDisplay extends BaseController
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
		// Explicitly set the view
		$this->input->set('view', 'import');
		
		// Register view path
		$this->addViewPath(JPATH_COMPONENT_ADMINISTRATOR . '/views');
		
		// Get the view class
		$viewClass = 'CategoryImportViewImport';
		$viewFile = JPATH_COMPONENT_ADMINISTRATOR . '/views/import/view.html.php';
		
		// Load the view file if it exists and class not loaded
		if (file_exists($viewFile) && !class_exists($viewClass)) {
			require_once $viewFile;
		}
		
		// Create an instance of the view
		if (class_exists($viewClass)) {
			$view = new $viewClass(['base_path' => JPATH_COMPONENT_ADMINISTRATOR]);
			
			// Get the model for this view
			$model = $this->getModel('import');
			if ($model) {
				$view->setModel($model, true);
			}
			
			// Display the view
			$view->display();
			
			return $this;
		}
		
		// If view class not found, use parent display
		return parent::display();
	}
}
