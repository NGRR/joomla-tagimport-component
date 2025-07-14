<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_tagimport
 *
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

// Import legacy model libraries for backward compatibility
jimport('joomla.application.component.model');

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\Component\Tags\Administrator\Table\TagTable;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\Database\ParameterType;

/**
 * Tag Import Model
 *
 * @since  1.0.0
 */
class TagImportModelImport extends BaseDatabaseModel
{	/**
	 * Constructor
	 *
	 * @param   array  $config  Configuration array
	 *
	 * @since   1.0.0
	 */
	public function __construct($config = [])
	{
		parent::__construct($config);
	}
	
	/**
	 * Import tags from a JSON file
	 *
	 * @param   array  $tags  Tags to import
	 *
	 * @return  array  Results array with success and count info
	 *
	 * @since   1.0.0
	 */
	public function importTags(array $tags): array
	{
		$db = $this->getDatabase();
		$app = Factory::getApplication();
		$user = $app->getIdentity();
        
		// Statistics
		$stats = [
			'imported' => 0,
			'skipped' => 0,
			'errors' => []
		];
		
		// Get the component parameters
		$params = \Joomla\CMS\Component\ComponentHelper::getParams('com_tagimport');
		$trackImportedTags = $params->get('track_imported', 1);
		
		// Start transaction
		$db->transactionStart();
		
		try {
			foreach ($tags as $tagData) {
				// Skip the tag if it doesn't have the required fields
				if (!isset($tagData['title']) || empty($tagData['title'])) {
					$stats['skipped']++;
					continue;
				}
				
				// Generate alias if not present
				if (!isset($tagData['alias']) || empty($tagData['alias'])) {
					$tagData['alias'] = OutputFilter::stringURLSafe($tagData['title']);
				}
				
				// Check if tag with this alias already exists
				$query = $db->getQuery(true)
					->select('id')
					->from('#__tags')
					->where('alias = ' . $db->quote($tagData['alias']));
				$db->setQuery($query);
				
				if ($db->loadResult()) {
					$stats['skipped']++;
					continue;
				}
				
				// Create table instance
				$table = new TagTable($db);
				
				// Prepare tag data
				$data = [
					'title' => $tagData['title'],
					'alias' => $tagData['alias'],
					'description' => $tagData['description'] ?? '',
					'published' => $tagData['published'] ?? 1,
					'access' => $tagData['access'] ?? 1,
					'language' => $tagData['language'] ?? '*',
					'note' => $tagData['note'] ?? '',
					'metadesc' => $tagData['metadesc'] ?? '',
					'metakey' => $tagData['metakey'] ?? '',
					'metadata' => $tagData['metadata'] ?? '',
					'created_user_id' => $tagData['created_user_id'] ?? $user->id,
					'created_time' => $tagData['created_time'] ?? date('Y-m-d H:i:s'),
					'modified_user_id' => $tagData['modified_user_id'] ?? $user->id,
					'modified_time' => $tagData['modified_time'] ?? date('Y-m-d H:i:s'),
					'version' => $tagData['version'] ?? 1,
					'parent_id' => $tagData['parent_id'] ?? 1,
					'path' => $this->validateTagPath($tagData['path'] ?? ''),
					'level' => $tagData['level'] ?? 0,
					'hits' => $tagData['hits'] ?? 0,
					'publish_up' => $tagData['publish_up'] ?? null,
					'publish_down' => $tagData['publish_down'] ?? null
				];
				
				// Try to save the tag
				if ($table->save($data)) {
					$stats['imported']++;
					
					// Track imported tag if enabled
					if ($trackImportedTags) {
						$this->trackImportedTag($table->id, $tagData['title']);
					}
				} else {
					$stats['skipped']++;
					$stats['errors'][] = 'Error saving tag "' . $tagData['title'] . '": ' . $table->getError();
				}
			}
			
			// Commit transaction
			$db->transactionCommit();
			
		} catch (\Exception $e) {
			$db->transactionRollback();
			throw $e;
		}
		
		return $stats;
	}
	
	/**
	 * Reset imported tags
	 *
	 * @return  int  Number of tags deleted
	 *
	 * @since   1.0.0
	 */
	public function resetImportedTags(): int
	{
		$db = $this->getDatabase();
		$deletedCount = 0;
		
		try {
			// Get imported tags from tracking table
			$query = $db->getQuery(true)
				->select('tag_id')
				->from('#__tagimport_tracking');
			
			$db->setQuery($query);
			$importedTagIds = $db->loadColumn();
			
			if (empty($importedTagIds)) {
				return 0;
			}
			
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
			
		} catch (\Exception $e) {
			throw new \Exception($e->getMessage());
		}
		
		return $deletedCount;
	}
	
	/**
	 * Batch move tags
	 *
	 * @param   array  $tagIds   Tag IDs to move
	 * @param   int    $parentId New parent ID
	 *
	 * @return  bool   True on success
	 *
	 * @since   1.0.0
	 */
	public function batchMove($tagIds, $parentId)
	{
		$db = $this->getDatabase();
		
		try {
			foreach ($tagIds as $tagId) {
				$query = $db->getQuery(true)
					->update('#__tags')
					->set('parent_id = ' . (int) $parentId)
					->where('id = ' . (int) $tagId);
				
				$db->setQuery($query);
				$db->execute();
			}
			
			return true;
		} catch (\Exception $e) {
			return false;
		}
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
			$db = $this->getDatabase();
			
			// Create tracking table if it doesn't exist
			$this->createTrackingTableIfNeeded();
			
			$query = $db->getQuery(true)
				->insert('#__tagimport_tracking')
				->columns('tag_id, tag_title, imported_date')
				->values((int) $tagId . ', ' . $db->quote($tagTitle) . ', ' . $db->quote(date('Y-m-d H:i:s')));
			
			$db->setQuery($query);
			$db->execute();
			
		} catch (\Exception $e) {
			// Log error but don't stop the import process
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
		$db = $this->getDatabase();
		
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
		}
	}
}
