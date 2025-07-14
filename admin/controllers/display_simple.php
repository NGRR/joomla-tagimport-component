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
use Joomla\CMS\Log\Log;

// Add logging
Log::addLogger(
    ['text_file' => 'categoryimport_controller_simple.php'],
    Log::ALL,
    ['categoryimport.controller.simple']
);

Log::add('Simple display controller loaded', Log::INFO, 'categoryimport.controller.simple');

/**
 * Simple Category Import Display Controller for debugging
 *
 * @since  1.0.0
 */
class CategoryImportControllerDisplaySimple extends BaseController
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
	 */	public function display($cachable = false, $urlparams = [])
	{
		Log::add('Simple display method called - testing original view', Log::INFO, 'categoryimport.controller.simple');
		
		// Test Phase 1: Try to load the original view without model
		$this->input->set('view', 'import');
		$this->addViewPath(JPATH_COMPONENT_ADMINISTRATOR . '/views');
		
		$viewClass = 'CategoryImportViewImport';
		$viewFile = JPATH_COMPONENT_ADMINISTRATOR . '/views/import/view.html.php';
		
		if (file_exists($viewFile) && !class_exists($viewClass)) {
			Log::add('Loading original view file: ' . $viewFile, Log::INFO, 'categoryimport.controller.simple');
			require_once $viewFile;
		}
		
		if (class_exists($viewClass)) {
			Log::add('Creating original view instance (without model)', Log::INFO, 'categoryimport.controller.simple');
			try {
				$view = new $viewClass(['base_path' => JPATH_COMPONENT_ADMINISTRATOR]);
				
				// Override the display method to prevent template loading issues
				echo '<div style="padding: 20px; background: #fff; margin: 20px; border: 1px solid #ddd;">';
				echo '<h1>Testing Original View (Phase 1)</h1>';
				echo '<div class="alert alert-success">Original view class loaded successfully!</div>';
				echo '<p>View class: ' . get_class($view) . '</p>';
				echo '<p>Base path: ' . JPATH_COMPONENT_ADMINISTRATOR . '</p>';
				echo '<p>Template paths: ' . print_r($view->get('_path'), true) . '</p>';
				echo '</div>';
				
				Log::add('Original view created successfully without model', Log::INFO, 'categoryimport.controller.simple');
				
			} catch (Exception $e) {
				Log::add('Error creating original view: ' . $e->getMessage(), Log::ERROR, 'categoryimport.controller.simple');
				echo '<div style="padding: 20px; color: red;">Error: ' . $e->getMessage() . '</div>';
			}
		} else {
			Log::add('Original view class not found, falling back to simple message', Log::WARNING, 'categoryimport.controller.simple');
			
			// Fallback to simple message
			echo '<div style="padding: 20px; background: #f0f0f0; margin: 20px; border: 1px solid #ccc;">';
			echo '<h1>CategoryImport Component - Debug Mode</h1>';
			echo '<p>Component is working correctly!</p>';
			echo '<p>Current user: ' . Factory::getUser()->name . '</p>';
			echo '<p>User groups: ' . implode(', ', Factory::getUser()->groups) . '</p>';
			echo '<p>Timestamp: ' . date('Y-m-d H:i:s') . '</p>';
			echo '</div>';
		}
		
		return $this;
	}
}
