<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_tagimport
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
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Table\Table;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Component\Tags\Administrator\Table\TagTable;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Log\Log;

/**
 * Tag Import Controller
 *
 * @since  1.0.0
 */
class TagImportControllerImport extends BaseController
{
	/**
	 * Constructor.
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 *
	 * @since   1.0.0
	 */
	public function __construct($config = [])
	{
		parent::__construct($config);
		
		// Initialize JLog for TagImport
		Log::addLogger(
			[
				'text_file' => 'tagimport.php',
				'text_entry_format' => '{DATETIME} {PRIORITY} {CATEGORY} {MESSAGE}'
			],
			Log::ALL,
			['com_tagimport']
		);
		
		$this->logDebug('TagImportControllerImport initialized');
	}
	
	/**
	 * Method to get a model object, loading it if required.
	 *
	 * @param   string  $name    The model name. Optional.
	 * @param   string  $prefix  The class prefix. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel  The model.
	 *
	 * @since   1.0.0
	 */
	public function getModel($name = 'Import', $prefix = 'TagImportModel', $config = [])
	{
		$model = parent::getModel($name, $prefix, $config);
		
		return $model;
	}
	
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
		// Log debug information
		$this->logDebug('Método display() llamado en TagImportControllerImport');
		
		// Set default view
		$this->input->set('view', 'import');
		
		// Check if we need to handle specific layout
		$layout = $this->input->get('layout', '');
		$this->logDebug('Layout solicitado: ' . $layout);
		
		// If preview layout is requested, ensure it's set
		if ($layout === 'preview') {
			$this->input->set('layout', 'preview');
			$this->logDebug('Layout preview configurado');
		}
		
		// Display the view
		return parent::display();
	}
	
	/**
	 * Upload JSON file with tags
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function upload()
	{
		// Check for request forgeries.
		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
		
		$this->logDebug('Método upload() iniciado para tags');
		
		$app = Factory::getApplication();
		$input = $app->getInput();
		
		// Check if file was uploaded
		$files = $input->files->get('jform', [], 'array');
		
		if (empty($files['jsonfile']['name'])) {
			$app->enqueueMessage(Text::_('COM_TAGIMPORT_NO_FILE_SELECTED'), 'error');
			$this->setRedirect(Route::_('index.php?option=com_tagimport&view=import', false));
			return;
		}
		
		$file = $files['jsonfile'];
		
		// Validate file type
		$allowedTypes = ['application/json', 'text/json', 'text/plain'];
		if (!in_array($file['type'], $allowedTypes) && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'json') {
			$app->enqueueMessage(Text::_('COM_TAGIMPORT_INVALID_FILE_TYPE'), 'error');
			$this->setRedirect(Route::_('index.php?option=com_tagimport&view=import', false));
			return;
		}
		
		// Check for upload errors
		if ($file['error'] !== UPLOAD_ERR_OK) {
			$app->enqueueMessage(Text::_('COM_TAGIMPORT_UPLOAD_ERROR'), 'error');
			$this->setRedirect(Route::_('index.php?option=com_tagimport&view=import', false));
			return;
		}
		
		// Read file content
		$content = file_get_contents($file['tmp_name']);
		
		if ($content === false) {
			$app->enqueueMessage(Text::_('COM_TAGIMPORT_UPLOAD_ERROR'), 'error');
			$this->setRedirect(Route::_('index.php?option=com_tagimport&view=import', false));
			return;
		}
		
		// Parse JSON
		$jsonData = json_decode($content, true);
		
		if (json_last_error() !== JSON_ERROR_NONE) {
			$app->enqueueMessage(Text::_('COM_TAGIMPORT_INVALID_JSON'), 'error');
			$this->setRedirect(Route::_('index.php?option=com_tagimport&view=import', false));
			return;
		}
		
		// Extract tags array from JSON structure
		if (isset($jsonData['value']) && is_array($jsonData['value'])) {
			$tags = $jsonData['value'];
		} elseif (is_array($jsonData)) {
			// If it's already a direct array, use it
			$tags = $jsonData;
		} else {
			$tags = [];
		}
		
		// Validate structure
		if (!is_array($tags) || empty($tags)) {
			$app->enqueueMessage(Text::_('COM_TAGIMPORT_INVALID_STRUCTURE'), 'error');
			$this->setRedirect(Route::_('index.php?option=com_tagimport&view=import', false));
			return;
		}
		
		// Validate tag structure
		$tagIndex = 0;
		$pathWarnings = 0;
		foreach ($tags as $tag) {
			$tagIndex++;
			if (!isset($tag['title']) || !isset($tag['alias'])) {
				$app->enqueueMessage(Text::sprintf('COM_TAGIMPORT_INVALID_TAG_STRUCTURE', $tagIndex), 'error');
				$this->setRedirect(Route::_('index.php?option=com_tagimport&view=import', false));
				return;
			}
			
			// Check for long paths that will be truncated
			if (isset($tag['path']) && strlen($tag['path']) > 255) {
				$pathWarnings++;
				$this->logDebug("Warning: Tag #{$tagIndex} '{$tag['title']}' has a path longer than 255 characters and will be truncated during import.");
			}
		}
		
		// Show warning if some paths will be truncated
		if ($pathWarnings > 0) {
			$app->enqueueMessage("Warning: {$pathWarnings} tag(s) have paths longer than 255 characters and will be truncated during import. Check the debug log for details.", 'warning');
		}
		
		// Store tags in session for preview
		$session = Factory::getSession();
		$session->set('tagimport.tags', $tags);
		
		$this->logDebug('Tags cargados en sesión: ' . count($tags));
		
		$app->enqueueMessage(Text::sprintf('COM_TAGIMPORT_UPLOAD_SUCCESS', count($tags)), 'success');
		$this->setRedirect(Route::_('index.php?option=com_tagimport&view=import&layout=preview', false));
	}
	
	/**
	 * Import tags from session
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function import()
	{
		// Check for request forgeries.
		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
		
		$this->logDebug('Método import() iniciado para tags');
		
		$app = Factory::getApplication();
		$session = Factory::getSession();
		
		// Get tags from session
		$tags = $session->get('tagimport.tags', []);
		
		if (empty($tags)) {
			$app->enqueueMessage(Text::_('COM_TAGIMPORT_NO_TAGS_FOUND'), 'error');
			$this->setRedirect(Route::_('index.php?option=com_tagimport&view=import', false));
			return;
		}
		
		$db = Factory::getDbo();
		$importedCount = 0;
		$skippedCount = 0;
		
		foreach ($tags as $tag) {
			try {
				// Check if tag already exists
				$query = $db->getQuery(true)
					->select('id')
					->from('#__tags')
					->where('alias = ' . $db->quote($tag['alias']));
				
				$db->setQuery($query);
				$existingTag = $db->loadResult();
				
				if ($existingTag) {
					$skippedCount++;
					$this->logDebug('Tag skipped (already exists): ' . $tag['title']);
					continue;
				}
				
				// Create new tag
				// Verificar si la clase TagTable existe antes de obtener la instancia
				if (!class_exists('Joomla\\Component\\Tags\\Administrator\\Table\\TagTable')) {
					$this->logDebug('Error: La clase Joomla\\Component\\Tags\\Administrator\\Table\\TagTable no existe.');
					$skippedCount++;
					continue;
				}
				
				// Use direct instantiation for Joomla 5.x component tables
				$tagTable = new TagTable($db);
				
				// Validar que $tagTable se creó correctamente
				if (!$tagTable) {
					$this->logDebug('Error: No se pudo crear la instancia de TagTable.');
					$skippedCount++;
					continue;
				}
				
				// Validate and truncate path if necessary (max 255 characters for MySQL varchar(255))
				$originalPath = $tag['path'] ?? '';
				$validatedPath = $this->validateTagPath($originalPath);
				
				$tagData = [
					'title' => $tag['title'],
					'alias' => $tag['alias'],
					'description' => $tag['description'] ?? '',
					'published' => $tag['published'] ?? 1,
					'access' => $tag['access'] ?? 1,
					'language' => $tag['language'] ?? '*',
					'note' => $tag['note'] ?? '',
					'metadesc' => $tag['metadesc'] ?? '',
					'metakey' => $tag['metakey'] ?? '',
					'metadata' => $tag['metadata'] ?? '',
					'created_user_id' => $tag['created_user_id'] ?? Factory::getUser()->id,
					'created_time' => $tag['created_time'] ?? date('Y-m-d H:i:s'),
					'modified_user_id' => $tag['modified_user_id'] ?? Factory::getUser()->id,
					'modified_time' => $tag['modified_time'] ?? date('Y-m-d H:i:s'),
					'version' => $tag['version'] ?? 1,
					'parent_id' => $tag['parent_id'] ?? 1,
					'path' => $validatedPath,
					'lft' => $tag['lft'] ?? 0,
					'rgt' => $tag['rgt'] ?? 0,
					'level' => $tag['level'] ?? 0,
					'hits' => $tag['hits'] ?? 0,
					'publish_up' => $tag['publish_up'] ?? null,
					'publish_down' => $tag['publish_down'] ?? null
				];
				
				// Log path validation if truncation occurred
				if ($originalPath !== $validatedPath) {
					$this->logDebug("Path truncated for tag '{$tag['title']}': '{$originalPath}' -> '{$validatedPath}'");
				}
				
				// Validar datos antes de guardar
				if (empty($tagData['title']) || empty($tagData['alias'])) {
					$this->logDebug('Error: Datos incompletos para el tag: ' . json_encode($tagData));
					$skippedCount++;
					continue;
				}
				
				// Validar unicidad de alias (doble verificación)
				$query = $db->getQuery(true)
					->select('COUNT(*)')
					->from('#__tags')
					->where('alias = ' . $db->quote($tagData['alias']));
				$db->setQuery($query);
				$aliasExists = $db->loadResult();

				if ($aliasExists) {
					$this->logDebug('Error: Alias duplicado para el tag: ' . $tagData['alias']);
					$skippedCount++;
					continue;
				}
				
				// Registrar datos antes de guardar
				$this->logDebug('Intentando guardar el tag: ' . json_encode($tagData));
				
				if ($tagTable->save($tagData)) {
					$importedCount++;
					$this->logDebug('Tag importado: ' . $tag['title']);
					
					// Track imported tag for potential reset functionality
					$this->trackImportedTag($tagTable->id, $tag['title']);
				} else {
					$this->logDebug('Error importando tag: ' . $tag['title'] . ' - ' . $tagTable->getError());
					$skippedCount++;
				}
			} catch (Exception $e) {
				$this->logDebug('Excepción importando tag: ' . $tag['title'] . ' - ' . $e->getMessage());
				$skippedCount++;
			}
		}
		
		// Clear session
		$session->clear('tagimport.tags');
		
		$app->enqueueMessage(Text::sprintf('COM_TAGIMPORT_IMPORT_SUCCESS', $importedCount, $skippedCount), 'success');
		$this->setRedirect(Route::_('index.php?option=com_tagimport&view=import', false));
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
		// Check for request forgeries.
		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
		
		$this->logDebug('Método reset() iniciado para tags');
		
		$app = Factory::getApplication();
		$db = Factory::getDbo();
		
		try {
			// Get imported tags from tracking table
			$query = $db->getQuery(true)
				->select('tag_id')
				->from('#__tagimport_tracking');
			
			$db->setQuery($query);
			$importedTagIds = $db->loadColumn();
			
			if (empty($importedTagIds)) {
				$app->enqueueMessage(Text::_('COM_TAGIMPORT_ERROR_NO_TRACKING_TABLE'), 'warning');
				$this->setRedirect(Route::_('index.php?option=com_tagimport&view=import', false));
				return;
			}
			
			$deletedCount = 0;
			
			// Delete tags
			foreach ($importedTagIds as $tagId) {
				$query = $db->getQuery(true)
					->delete('#__tags')
					->where('id = ' . (int) $tagId);
				
				$db->setQuery($query);
				if ($db->execute()) {
					$deletedCount++;
				}
			}
			
			// Clear tracking table
			$query = $db->getQuery(true)
				->delete('#__tagimport_tracking');
			$db->setQuery($query);
			$db->execute();
			
			$this->logDebug("Tags eliminados en reset: {$deletedCount}");
			
			$app->enqueueMessage(Text::sprintf('COM_TAGIMPORT_RESET_SUCCESS', $deletedCount), 'success');
		} catch (\Exception $e) {
			$this->logDebug('Error en reset: ' . $e->getMessage());
			$app->enqueueMessage(Text::sprintf('COM_TAGIMPORT_RESET_ERROR', $e->getMessage()), 'error');
		}
		
		$this->setRedirect(Route::_('index.php?option=com_tagimport&view=import', false));
	}
	
	/**
	 * Method to handle batch operations for tags
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function batch()
	{
		// Check for request forgeries
		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		// Get the model
		$model = $this->getModel('Import');
		$vars = $this->input->post->get('batch', array(), 'array');
		$cids = $this->input->post->get('cid', array(), 'array');

		// Sanitize inputs
		$cids = ArrayHelper::toInteger($cids);
		$parent_id = isset($vars['parent_id']) ? (int) $vars['parent_id'] : 1;

		// Validate inputs
		if (empty($cids))
		{
			$this->setMessage(Text::_('COM_TAGIMPORT_ERROR_NO_TAGS_SELECTED'), 'error');
			$this->setRedirect(Route::_('index.php?option=com_tagimport&view=import', false));
			return;
		}

		// Perform batch operation
		if ($model && method_exists($model, 'batchMove') && $model->batchMove($cids, $parent_id))
		{
			$this->setMessage(Text::plural('COM_TAGIMPORT_BATCH_SUCCESS', count($cids)));
		}
		else
		{
			$this->setMessage($model ? $model->getError() : 'Model not found', 'error');
		}

		$this->setRedirect(Route::_('index.php?option=com_tagimport&view=import', false));
	}
	
	/**
	 * Validate and truncate tag path if necessary
	 *
	 * @param   string  $path  Original path
	 *
	 * @return  string  Validated path (truncated if necessary)
	 *
	 * @since   1.0.0
	 */
	private function validateTagPath($path)
	{
		// Maximum length for path column in jos_tags table (MySQL varchar(255))
		$maxLength = 255;
		
		// If path is empty or within limits, return as is
		if (empty($path) || strlen($path) <= $maxLength) {
			return $path;
		}
		
		// Path is too long, need to truncate intelligently
		// Try to preserve the most meaningful part of the path
		
		// Option 1: Keep the end of the path (most specific part)
		// This preserves the actual tag name/identifier which is usually at the end
		$truncated = '...' . substr($path, -(($maxLength - 3)));
		
		// Ensure we don't exceed the limit even with our truncation
		if (strlen($truncated) > $maxLength) {
			$truncated = substr($truncated, 0, $maxLength);
		}
		
		return $truncated;
	}
	
	/**
	 * Track imported tag for potential reset functionality
	 *
	 * @param   int     $tagId     The ID of the imported tag
	 * @param   string  $tagTitle  The title of the imported tag
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function trackImportedTag($tagId, $tagTitle)
	{
		try {
			$db = Factory::getDbo();
			
			// Create tracking table if it doesn't exist
			$this->createTrackingTableIfNeeded();
			
			$query = $db->getQuery(true)
				->insert('#__tagimport_tracking')
				->columns('tag_id, tag_title, imported_date')
				->values((int) $tagId . ', ' . $db->quote($tagTitle) . ', ' . $db->quote(date('Y-m-d H:i:s')));
			
			$db->setQuery($query);
			$db->execute();
			
			$this->logDebug("Tag tracked: ID {$tagId}, Title: {$tagTitle}");
		} catch (Exception $e) {
			$this->logDebug('Error tracking tag: ' . $e->getMessage());
		}
	}
	
	/**
	 * Create tracking table if it doesn't exist
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function createTrackingTableIfNeeded()
	{
		$db = Factory::getDbo();
		
		// Check if table exists
		$tables = $db->getTableList();
		$prefix = $db->getPrefix();
		$tableName = $prefix . 'tagimport_tracking';
		
		if (!in_array($tableName, $tables)) {
			$query = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`tag_id` int(11) NOT NULL,
				`tag_title` varchar(255) NOT NULL,
				`imported_date` datetime NOT NULL,
				PRIMARY KEY (`id`),
				KEY `idx_tag_id` (`tag_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
			
			$db->setQuery($query);
			$db->execute();
			
			$this->logDebug('Tracking table created: ' . $tableName);
		}
	}
	
	/**
	 * Log debug message using Joomla Log
	 *
	 * @param   string  $message  Debug message
	 * @param   string  $level    Log level (default: debug)
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function logDebug($message, $level = 'debug')
	{
		$logLevel = Log::DEBUG;
		
		switch (strtolower($level)) {
			case 'info':
				$logLevel = Log::INFO;
				break;
			case 'warning':
				$logLevel = Log::WARNING;
				break;
			case 'error':
				$logLevel = Log::ERROR;
				break;
			case 'debug':
			default:
				$logLevel = Log::DEBUG;
				break;
		}
		
		Log::add($message, $logLevel, 'com_tagimport');
	}
}
