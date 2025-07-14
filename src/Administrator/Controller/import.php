<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_categoryimport
 *
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CategoryImport\Component\CategoryImport\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Table\Table;

/**
 * Category Import Controller
 *
 * @since  1.0.0
 */
class ImportController extends BaseController
{
	/**
	 * Upload JSON file with categories
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function upload()
	{
		// Check for request forgeries
		$this->checkToken();
		
		$app = Factory::getApplication();
		$input = $app->input;
		$files = $input->files->get('jform', [], 'array');
		
		// If no file was uploaded
		if (empty($files['jsonfile']['name'])) {
			$app->enqueueMessage(Text::_('COM_CATEGORYIMPORT_NO_FILE_SELECTED'), 'warning');
			$app->redirect(Route::_('index.php?option=com_categoryimport', false));
			return;
		}
		
		$allowedExtensions = ['json'];
		$filename = $files['jsonfile']['name'];
		$extension = pathinfo($filename, PATHINFO_EXTENSION);
		
		// Check if the extension is allowed
		if (!in_array(strtolower($extension), $allowedExtensions)) {
			$app->enqueueMessage(Text::_('COM_CATEGORYIMPORT_INVALID_FILE_TYPE'), 'error');
			$app->redirect(Route::_('index.php?option=com_categoryimport', false));
			return;
		}
		
		// Upload path
		$uploadPath = JPATH_ADMINISTRATOR . '/tmp/com_categoryimport';
		
		// Create directory if it doesn't exist
		if (!file_exists($uploadPath)) {
			mkdir($uploadPath, 0755, true);
		}
		
		$uploadFileName = uniqid('categories_') . '.json';
		$uploadFilePath = $uploadPath . '/' . $uploadFileName;
		
		// Upload file
		if (!move_uploaded_file($files['jsonfile']['tmp_name'], $uploadFilePath)) {
			$app->enqueueMessage(Text::_('COM_CATEGORYIMPORT_UPLOAD_ERROR'), 'error');
			$app->redirect(Route::_('index.php?option=com_categoryimport', false));
			return;
		}
		
		// Read the uploaded file
		$jsonContent = file_get_contents($uploadFilePath);
		$categories = json_decode($jsonContent, true);
		
		// Check if the JSON is valid
		if (json_last_error() !== JSON_ERROR_NONE) {
			$app->enqueueMessage(Text::_('COM_CATEGORYIMPORT_INVALID_JSON'), 'error');
			$app->redirect(Route::_('index.php?option=com_categoryimport', false));
			return;
		}
		
		// Check if the JSON has the expected structure
		if (!isset($categories['categories']) || !is_array($categories['categories']) || count($categories['categories']) === 0) {
			$app->enqueueMessage(Text::_('COM_CATEGORYIMPORT_INVALID_STRUCTURE'), 'error');
			$app->redirect(Route::_('index.php?option=com_categoryimport', false));
			return;
		}
		
		// Store file information in session
		$session = Factory::getSession();
		$session->set('categoryimport.file', $uploadFilePath);
		$session->set('categoryimport.categories', $categories['categories']);
		$session->set('categoryimport.total', count($categories['categories']));
		
		// Redirect to preview page
		$app->enqueueMessage(Text::sprintf('COM_CATEGORYIMPORT_UPLOAD_SUCCESS', count($categories['categories'])), 'success');
		$app->redirect(Route::_('index.php?option=com_categoryimport&view=import&layout=preview', false));
	}
	
	/**
	 * Import categories from the uploaded JSON file
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function import()
	{
		// Check for request forgeries
		$this->checkToken();
		
		$app = Factory::getApplication();
		$session = Factory::getSession();
		$model = $this->getModel('Import');
		
		// Get categories from session
		$categories = $session->get('categoryimport.categories', []);
		
		if (empty($categories)) {
			$app->enqueueMessage(Text::_('COM_CATEGORYIMPORT_NO_CATEGORIES_FOUND'), 'error');
			$app->redirect(Route::_('index.php?option=com_categoryimport', false));
			return;
		}
		
		// Import categories
		try {
			$result = $model->importCategories($categories);
			
			// Delete the temporary file
			$uploadFile = $session->get('categoryimport.file', '');
			if (file_exists($uploadFile)) {
				unlink($uploadFile);
			}
			
			// Clear the session data
			$session->clear('categoryimport.file');
			$session->clear('categoryimport.categories');
			$session->clear('categoryimport.total');
			
			$app->enqueueMessage(Text::sprintf('COM_CATEGORYIMPORT_IMPORT_SUCCESS', $result['imported'], $result['skipped']), 'success');
		} catch (\Exception $e) {
			$app->enqueueMessage(Text::sprintf('COM_CATEGORYIMPORT_IMPORT_ERROR', $e->getMessage()), 'error');
		}
		
		$app->redirect(Route::_('index.php?option=com_categoryimport', false));
	}
	
	/**
	 * Reset imported categories
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function reset()
	{
		// Check for request forgeries
		$this->checkToken();
		
		$app = Factory::getApplication();
		$model = $this->getModel('Import');
		
		try {
			$count = $model->resetImportedCategories();
			$app->enqueueMessage(Text::sprintf('COM_CATEGORYIMPORT_RESET_SUCCESS', $count), 'success');
		} catch (\Exception $e) {
			$app->enqueueMessage(Text::sprintf('COM_CATEGORYIMPORT_RESET_ERROR', $e->getMessage()), 'error');
		}
		
		$app->redirect(Route::_('index.php?option=com_categoryimport', false));
	}
}
