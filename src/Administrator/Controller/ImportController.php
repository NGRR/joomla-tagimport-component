<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_tagimport
 *
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace TagImport\Component\TagImport\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Log\Log;

/**
 * Tag Import Controller
 *
 * @since  1.0.0
 */
class ImportController extends BaseController
{
	/**
	 * Upload JSON file with tags
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function upload()
	{
		Log::add('Upload method started.', Log::INFO, 'com_tagimport');

		// Check user permissions
		$user = Factory::getUser();
		if (!$user->authorise('tagimport.import', 'com_tagimport')) {
			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Check for request forgeries
		$this->checkToken();
		Log::add('Token check passed.', Log::INFO, 'com_tagimport');
		$app = Factory::getApplication();
		$input = $app->input;
		$files = $input->files->get('jform', [], 'array');

		// If no file was uploaded
		if (empty($files['jsonfile']['name'])) {
			$app->enqueueMessage(Text::_('COM_TAGIMPORT_NO_FILE_SELECTED'), 'warning');
			Log::add('No file selected for upload.', Log::WARNING, 'com_tagimport');
			$this->setRedirect(Route::_('index.php?option=com_tagimport', false));
			return;
		}

		$allowedExtensions = ['json'];
		$filename = $files['jsonfile']['name'];
		$extension = pathinfo($filename, PATHINFO_EXTENSION);
		// Check if the extension is allowed
		if (!in_array(strtolower($extension), $allowedExtensions)) {
			$app->enqueueMessage(Text::_('COM_TAGIMPORT_INVALID_FILE_TYPE'), 'error');
			Log::add('Invalid file type: ' . $extension, Log::ERROR, 'com_tagimport');
			$this->setRedirect(Route::_('index.php?option=com_tagimport', false));
			return;
		}

		// Upload path
		$uploadPath = JPATH_ADMINISTRATOR . '/tmp/com_tagimport';

		// Create directory if it doesn't exist
		if (!file_exists($uploadPath)) {
			mkdir($uploadPath, 0755, true);
			Log::add('Created upload directory: ' . $uploadPath, Log::INFO, 'com_tagimport');
		}

		$uploadFileName = uniqid('tags_') . '.json';
		$uploadFilePath = $uploadPath . '/' . $uploadFileName;
		// Upload file
		if (!move_uploaded_file($files['jsonfile']['tmp_name'], $uploadFilePath)) {
			$app->enqueueMessage(Text::_('COM_TAGIMPORT_UPLOAD_ERROR'), 'error');
			Log::add('Failed to move uploaded file.', Log::ERROR, 'com_tagimport');
			$this->setRedirect(Route::_('index.php?option=com_tagimport', false));
			return;
		}

		Log::add('File uploaded successfully: ' . $uploadFilePath, Log::INFO, 'com_tagimport');

		// Read the uploaded file
		$jsonContent = file_get_contents($uploadFilePath);
		$tags = json_decode($jsonContent, true);
		// Check if the JSON is valid
		if (json_last_error() !== JSON_ERROR_NONE) {
			$app->enqueueMessage(Text::_('COM_TAGIMPORT_INVALID_JSON'), 'error');
			Log::add('Invalid JSON format: ' . json_last_error_msg(), Log::ERROR, 'com_tagimport');
			$this->setRedirect(Route::_('index.php?option=com_tagimport', false));
			return;
		}
		// Check if the JSON has the expected structure
		if (!isset($tags['tags']) || !is_array($tags['tags']) || count($tags['tags']) === 0) {
			$app->enqueueMessage(Text::_('COM_TAGIMPORT_INVALID_STRUCTURE'), 'error');
			Log::add('Invalid JSON structure.', Log::ERROR, 'com_tagimport');
			$this->setRedirect(Route::_('index.php?option=com_tagimport', false));
			return;
		}

		Log::add('JSON file validated successfully.', Log::INFO, 'com_tagimport');

		// Store file information in session
		$session = Factory::getSession();
		$session->set('tagimport.file', $uploadFilePath);
		$session->set('tagimport.tags', $tags['tags']);
		$session->set('tagimport.total', count($tags['tags']));
		// Redirect to preview page
		$app->enqueueMessage(Text::sprintf('COM_TAGIMPORT_UPLOAD_SUCCESS', count($tags['tags'])), 'success');
		Log::add('Redirecting to preview page with ' . count($tags['tags']) . ' tags.', Log::INFO, 'com_tagimport');
		$this->setRedirect(Route::_('index.php?option=com_tagimport&view=import&layout=preview', false));
	}
	
	/**
	 * Import tags from the uploaded JSON file
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function import()
	{
		// Check user permissions
		$user = Factory::getUser();
		if (!$user->authorise('tagimport.import', 'com_tagimport')) {
			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Check for request forgeries
		$this->checkToken();
		
		$app = Factory::getApplication();
		$session = Factory::getSession();
		
		/** @var \TagImport\Component\TagImport\Administrator\Model\ImportModel $model */
		$model = $this->getModel('Import');
		
		// Get tags from session
		$tags = $session->get('tagimport.tags', []);
				if (empty($tags)) {
			$app->enqueueMessage(Text::_('COM_TAGIMPORT_NO_TAGS_FOUND'), 'error');
			$this->setRedirect(Route::_('index.php?option=com_tagimport', false));
			return;
		}
		
		// Import tags
		try {
			$result = $model->importTags($tags);
			
			// Delete the temporary file
			$uploadFile = $session->get('tagimport.file', '');
			if (file_exists($uploadFile)) {
				unlink($uploadFile);
			}
			
			// Clear the session data
			$session->clear('tagimport.file');
			$session->clear('tagimport.tags');
			$session->clear('tagimport.total');
					$app->enqueueMessage(Text::sprintf('COM_TAGIMPORT_IMPORT_SUCCESS', $result['imported'], $result['skipped']), 'success');
		} catch (\Exception $e) {
			$app->enqueueMessage(Text::sprintf('COM_TAGIMPORT_IMPORT_ERROR', $e->getMessage()), 'error');
		}
		
		$this->setRedirect(Route::_('index.php?option=com_tagimport', false));
	}
	
	/**
	 * Reset imported tags
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function reset()
	{
		// Check user permissions
		$user = Factory::getUser();
		if (!$user->authorise('core.delete', 'com_tagimport')) {
			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Check for request forgeries
		$this->checkToken();
		
		$app = Factory::getApplication();
		
		/** @var \TagImport\Component\TagImport\Administrator\Model\ImportModel $model */
		$model = $this->getModel('Import');
		
		try {
			$count = $model->resetImportedTags();			$app->enqueueMessage(Text::sprintf('COM_TAGIMPORT_RESET_SUCCESS', $count), 'success');
		} catch (\Exception $e) {
			$app->enqueueMessage(Text::sprintf('COM_TAGIMPORT_RESET_ERROR', $e->getMessage()), 'error');
		}
		
		$this->setRedirect(Route::_('index.php?option=com_tagimport', false));
	}
	
	/**
	 * Download the log file
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function downloadLog()
	{
		// Check user permissions
		$user = Factory::getUser();
		if (!$user->authorise('core.manage', 'com_tagimport')) {
			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$app = Factory::getApplication();
		$logFile = Factory::getConfig()->get('tmp_path') . '/tagimport.log.php';

		if (!file_exists($logFile)) {
			$app->enqueueMessage(Text::_('COM_TAGIMPORT_LOG_FILE_NOT_FOUND'), 'error');
			$this->setRedirect(Route::_('index.php?option=com_tagimport', false));
			return;
		}

		// Set headers for file download
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="tagimport.log.php"');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($logFile));

		// Clear output buffer and read the file
		ob_clean();
		flush();
		readfile($logFile);
		exit;
	}
}
