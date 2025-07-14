<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_tagimport
 *
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Registry\Registry;
use Joomla\CMS\Log\Log;

// Initialize JLog for TagImport
Log::addLogger(
    [
        'text_file' => 'tagimport.php',
        'text_entry_format' => '{DATETIME} {PRIORITY} {CATEGORY} {MESSAGE}'
    ],
    Log::ALL,
    ['com_tagimport']
);

// Load language files directly
$lang = Factory::getApplication()->getLanguage();
$lang->load('com_tagimport', JPATH_ADMINISTRATOR);

// Proper ACL check
$user = Factory::getUser();
if (!$user->authorise('core.manage', 'com_tagimport')) {
    throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
}

// Get component parameters
$app = Factory::getApplication();

// Get params from database
$db = Factory::getDbo();
$query = $db->getQuery(true)
    ->select('params')
    ->from('#__extensions')
    ->where('element = ' . $db->quote('com_tagimport'))
    ->where('type = ' . $db->quote('component'));

$db->setQuery($query);
$paramsString = $db->loadResult();

if ($paramsString) {
    $params = new Registry($paramsString);
} else {
    $params = new Registry();
}

$enableLogging = $params->get('enable_logging', 1);
$debugMode = $params->get('debug_mode', 0);

// Log component initialization only if logging is enabled
if ($enableLogging) {
    Log::add('Componente TagImport cargado correctamente - ' . date('Y-m-d H:i:s'), Log::INFO, 'com_tagimport');
    
    if ($debugMode) {
        Log::add('Modo debug activo', Log::DEBUG, 'com_tagimport');
    }
}

/**
 * Create tracking table if it doesn't exist
 */
function createTrackingTable()
{
    $db = Factory::getDbo();
    $tableName = $db->getPrefix() . 'tagimport_tracking';
    
    // Check if table exists
    $tables = $db->getTableList();
    if (in_array($tableName, $tables)) {
        Log::add('Tabla de tracking ya existe: ' . $tableName, Log::DEBUG, 'com_tagimport');
        return true;
    }
    
    Log::add('Creando tabla de tracking: ' . $tableName, Log::INFO, 'com_tagimport');
    
    $sql = "CREATE TABLE IF NOT EXISTS `" . $tableName . "` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tag_id` int(11) NOT NULL,
        `original_alias` varchar(191) NOT NULL,
        `imported_date` datetime NOT NULL,
        `imported_by` int(11) NOT NULL,
        `source_data` text,
        PRIMARY KEY (`id`),
        KEY `idx_tag_id` (`tag_id`),
        KEY `idx_alias` (`original_alias`),
        KEY `idx_imported_date` (`imported_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    try {
        $db->setQuery($sql);
        $result = $db->execute();
        
        if ($result) {
            Log::add('Tabla de tracking creada exitosamente', Log::INFO, 'com_tagimport');
            return true;
        } else {
            Log::add('Error creando tabla de tracking', Log::ERROR, 'com_tagimport');
            return false;
        }
    } catch (Exception $e) {
        Log::add('Excepción creando tabla de tracking: ' . $e->getMessage(), Log::ERROR, 'com_tagimport');
        return false;
    }
}

/**
 * Process tag import with robust SQL handling
 */
function processTagImport($data)
{
    Log::add('Iniciando proceso de importación de tags', Log::INFO, 'com_tagimport');
    
    try {
        $db = Factory::getDbo();
        $user = Factory::getUser();
        $importedCount = 0;
        $skippedCount = 0;
        $errors = [];
        
        // Create tracking table if needed
        createTrackingTable();
        
        // Disable strict mode temporarily for the import
        $db->setQuery("SET SESSION sql_mode = ''");
        $db->execute();
        Log::add('Modo SQL strict desactivado temporalmente', Log::DEBUG, 'com_tagimport');
        
        foreach ($data as $tagData) {
            try {
                $title = isset($tagData['title']) ? trim($tagData['title']) : '';
                $alias = isset($tagData['alias']) ? trim($tagData['alias']) : '';
                
                if (empty($title)) {
                    $errors[] = 'Tag sin título encontrado, omitido';
                    $skippedCount++;
                    continue;
                }
                
                // Generate alias if not provided
                if (empty($alias)) {
                    $alias = strtolower(str_replace([' ', '_'], '-', $title));
                    $alias = preg_replace('/[^a-z0-9\-]/', '', $alias);
                }
                
                // Check if tag already exists
                $query = $db->getQuery(true)
                    ->select('id')
                    ->from('#__tags')
                    ->where('alias = ' . $db->quote($alias));
                $db->setQuery($query);
                $existingId = $db->loadResult();
                
                if ($existingId) {
                    Log::add('Tag con alias "' . $alias . '" ya existe, omitido', Log::INFO, 'com_tagimport');
                    $skippedCount++;
                    continue;
                }
                
                // Prepare tag data with forced defaults
                $description = isset($tagData['description']) ? $tagData['description'] : '';
                $published = isset($tagData['published']) ? (int)$tagData['published'] : 1;
                $access = isset($tagData['access']) ? (int)$tagData['access'] : 1;
                $language = isset($tagData['language']) ? $tagData['language'] : '*';
                $note = isset($tagData['note']) ? $tagData['note'] : '';
                $metadesc = isset($tagData['metadesc']) ? $tagData['metadesc'] : '';
                $metakey = isset($tagData['metakey']) ? $tagData['metakey'] : '';
                
                $currentDate = Factory::getDate()->toSql();
                
                // Use direct SQL insert with all required fields explicitly set
                $sql = "INSERT INTO " . $db->quoteName('#__tags') . " (
                    " . $db->quoteName('parent_id') . ",
                    " . $db->quoteName('lft') . ",
                    " . $db->quoteName('rgt') . ",
                    " . $db->quoteName('level') . ",
                    " . $db->quoteName('path') . ",
                    " . $db->quoteName('title') . ",
                    " . $db->quoteName('alias') . ",
                    " . $db->quoteName('note') . ",
                    " . $db->quoteName('description') . ",
                    " . $db->quoteName('published') . ",
                    " . $db->quoteName('checked_out') . ",
                    " . $db->quoteName('checked_out_time') . ",
                    " . $db->quoteName('access') . ",
                    " . $db->quoteName('params') . ",
                    " . $db->quoteName('metadesc') . ",
                    " . $db->quoteName('metakey') . ",
                    " . $db->quoteName('metadata') . ",
                    " . $db->quoteName('created_user_id') . ",
                    " . $db->quoteName('created_time') . ",
                    " . $db->quoteName('created_by_alias') . ",
                    " . $db->quoteName('modified_user_id') . ",
                    " . $db->quoteName('modified_time') . ",
                    " . $db->quoteName('images') . ",
                    " . $db->quoteName('urls') . ",
                    " . $db->quoteName('hits') . ",
                    " . $db->quoteName('language') . ",
                    " . $db->quoteName('version') . "
                ) VALUES (
                    1, 0, 0, 1,
                    " . $db->quote($alias) . ",
                    " . $db->quote($title) . ",
                    " . $db->quote($alias) . ",
                    " . $db->quote($note) . ",
                    " . $db->quote($description) . ",
                    " . (int)$published . ", 0, NULL, " . (int)$access . ",
                    '{}',
                    " . $db->quote($metadesc) . ",
                    " . $db->quote($metakey) . ",
                    '{}',
                    " . (int)$user->id . ",
                    " . $db->quote($currentDate) . ",
                    '',
                    " . (int)$user->id . ",
                    " . $db->quote($currentDate) . ",
                    '{}', '{}', 0,
                    " . $db->quote($language) . ",
                    1
                )";
                
                $db->setQuery($sql);
                $result = $db->execute();
                
                if ($result) {
                    $tagId = $db->insertid();
                    Log::add('Tag "' . $title . '" importado exitosamente con ID: ' . $tagId, Log::INFO, 'com_tagimport');
                    
                    // Add to tracking table
                    $trackingData = [
                        'tag_id' => $tagId,
                        'original_alias' => $alias,
                        'imported_date' => $currentDate,
                        'imported_by' => $user->id,
                        'source_data' => json_encode($tagData)
                    ];
                    
                    $trackingInsert = $db->getQuery(true)
                        ->insert('#__tagimport_tracking')
                        ->columns(array_keys($trackingData))
                        ->values(implode(',', array_map([$db, 'quote'], $trackingData)));
                    
                    $db->setQuery($trackingInsert);
                    $db->execute();
                    
                    $importedCount++;
                } else {
                    $errors[] = 'Error insertando tag "' . $title . '"';
                    $skippedCount++;
                }
                
            } catch (Exception $e) {
                $errorMsg = 'Error procesando tag "' . ($title ?? 'unknown') . '": ' . $e->getMessage();
                $errors[] = $errorMsg;
                Log::add($errorMsg, Log::ERROR, 'com_tagimport');
                $skippedCount++;
            }
        }
        
        Log::add('Importación completada: ' . $importedCount . ' importados, ' . $skippedCount . ' omitidos', Log::INFO, 'com_tagimport');
        
        return [
            'success' => true,
            'imported' => $importedCount,
            'skipped' => $skippedCount,
            'errors' => $errors
        ];
        
    } catch (Exception $e) {
        $errorMsg = 'Error en proceso de importación: ' . $e->getMessage();
        Log::add($errorMsg, Log::ERROR, 'com_tagimport');
        
        return [
            'success' => false,
            'imported' => 0,
            'skipped' => 0,
            'error' => $errorMsg
        ];
    }
}

/**
 * Get tag import status
 */
function getTagImportStatus()
{
    $db = Factory::getDbo();
    $status = [
        'total_tags' => 0,
        'imported_tags' => 0,
        'tracking_table_exists' => false
    ];
    
    try {
        // Get total tags count
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__tags')
            ->where('id > 1'); // Exclude root tag
        $db->setQuery($query);
        $status['total_tags'] = (int) $db->loadResult();
        
        // Check if tracking table exists
        $tables = $db->getTableList();
        $trackingTable = $db->getPrefix() . 'tagimport_tracking';
        $status['tracking_table_exists'] = in_array($trackingTable, $tables);
        
        if ($status['tracking_table_exists']) {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from('#__tagimport_tracking');
            $db->setQuery($query);
            $status['imported_tags'] = (int) $db->loadResult();
        }
        
    } catch (Exception $e) {
        Log::add('Error obteniendo status: ' . $e->getMessage(), Log::ERROR, 'com_tagimport');
    }
    
    return $status;
}

/**
 * Reset imported tags
 */
function resetImportedTags()
{
    $db = Factory::getDbo();
    $deletedCount = 0;
    
    try {
        // Get tracking table name
        $trackingTable = $db->getPrefix() . 'tagimport_tracking';
        $tables = $db->getTableList();
        
        if (!in_array($trackingTable, $tables)) {
            throw new Exception(Text::_('COM_TAGIMPORT_ERROR_NO_TRACKING_TABLE'));
        }
        
        // Get imported tag IDs
        $query = $db->getQuery(true)
            ->select('tag_id')
            ->from('#__tagimport_tracking');
        $db->setQuery($query);
        $tagIds = $db->loadColumn();
        
        if (!empty($tagIds)) {
            // Delete tags
            $query = $db->getQuery(true)
                ->delete('#__tags')
                ->where('id IN (' . implode(',', array_map('intval', $tagIds)) . ')');
            $db->setQuery($query);
            $db->execute();
            $deletedCount = $db->getAffectedRows();
            
            // Clear tracking table
            $query = $db->getQuery(true)
                ->delete('#__tagimport_tracking');
            $db->setQuery($query);
            $db->execute();
            
            Log::add('Reset completado: ' . $deletedCount . ' tags eliminados', Log::INFO, 'com_tagimport');
        }
        
        return [
            'success' => true,
            'deleted_count' => $deletedCount
        ];
        
    } catch (Exception $e) {
        Log::add('Error en reset: ' . $e->getMessage(), Log::ERROR, 'com_tagimport');
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Get current status
$tagStatus = getTagImportStatus();

// Handle form submission
$message = '';
$messageType = '';
$previewData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = Factory::getApplication()->getFormToken();
    if (isset($_POST[$token]) && $_POST[$token] === '1') {
        
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            
            if ($action === 'upload') {
                // Handle file upload
                if (isset($_FILES['jsonfile']) && $_FILES['jsonfile']['error'] === 0) {
                    $uploadedFile = $_FILES['jsonfile'];
                    
                    // Validate file type
                    if ($uploadedFile['type'] !== 'application/json' && pathinfo($uploadedFile['name'], PATHINFO_EXTENSION) !== 'json') {
                        $message = Text::_('COM_TAGIMPORT_INVALID_FILE_TYPE');
                        $messageType = 'error';
                    } else {
                        $jsonContent = file_get_contents($uploadedFile['tmp_name']);
                        $data = json_decode($jsonContent, true);
                        
                        if ($data === null) {
                            $message = Text::_('COM_TAGIMPORT_INVALID_JSON');
                            $messageType = 'error';
                        } else if (!is_array($data) || empty($data)) {
                            $message = Text::_('COM_TAGIMPORT_INVALID_STRUCTURE');
                            $messageType = 'error';
                        } else {
                            // Store in session for preview
                            $session = Factory::getSession();
                            $session->set('tagimport_data', $data);
                            
                            $message = sprintf(Text::_('COM_TAGIMPORT_UPLOAD_SUCCESS'), count($data));
                            $messageType = 'success';
                            $previewData = $data;
                        }
                    }
                } else {
                    $message = Text::_('COM_TAGIMPORT_UPLOAD_ERROR');
                    $messageType = 'error';
                }
                
            } else if ($action === 'import') {
                // Handle import
                $session = Factory::getSession();
                $data = $session->get('tagimport_data');
                
                if (empty($data)) {
                    $message = Text::_('COM_TAGIMPORT_NO_TAGS_FOUND');
                    $messageType = 'error';
                } else {
                    $importResult = processTagImport($data);
                    
                    if ($importResult['success']) {
                        $session->clear('tagimport_data');
                        $message = sprintf(Text::_('COM_TAGIMPORT_IMPORT_SUCCESS'), $importResult['imported'], $importResult['skipped']);
                        $messageType = 'success';
                        
                        // Refresh status
                        $tagStatus = getTagImportStatus();
                    } else {
                        $message = sprintf(Text::_('COM_TAGIMPORT_IMPORT_ERROR'), $importResult['error']);
                        $messageType = 'error';
                    }
                }
                
            } else if ($action === 'reset') {
                // Handle reset
                $resetResult = resetImportedTags();
                
                if ($resetResult['success']) {
                    $message = sprintf(Text::_('COM_TAGIMPORT_RESET_SUCCESS'), $resetResult['deleted_count']);
                    $messageType = 'success';
                    
                    // Refresh status
                    $tagStatus = getTagImportStatus();
                } else {
                    $message = sprintf(Text::_('COM_TAGIMPORT_RESET_ERROR'), $resetResult['error']);
                    $messageType = 'error';
                }
            }
        }
    } else {
        $message = Text::_('JINVALID_TOKEN');
        $messageType = 'error';
    }
}

// Get session data for preview
if (!$previewData) {
    $session = Factory::getSession();
    $previewData = $session->get('tagimport_data');
}
?>

<!DOCTYPE html>
<html lang="<?php echo $app->getLanguage(); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo Text::_('COM_TAGIMPORT_TITLE'); ?> - <?php echo Text::_('JADMINISTRATION'); ?></title>
    
    <?php
    // Load Joomla's admin template CSS
    HTMLHelper::_('bootstrap.framework');
    HTMLHelper::_('behavior.core');
    ?>
    
    <style>
        body { font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .card { background: #ffffff; padding: 20px; border: 1px solid #dee2e6; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 14px; margin: 5px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="file"] { padding: 8px; border: 1px solid #ced4da; border-radius: 4px; width: 100%; max-width: 400px; }
        .status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .status-item { background: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center; }
        .status-number { font-size: 24px; font-weight: bold; color: #007bff; }
        .preview-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .preview-table th, .preview-table td { padding: 8px 12px; border: 1px solid #dee2e6; text-align: left; }
        .preview-table th { background: #f8f9fa; font-weight: bold; }
        .json-format { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; margin: 15px 0; }
        .json-format pre { margin: 0; font-family: 'Courier New', monospace; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo Text::_('COM_TAGIMPORT_TITLE'); ?></h1>
        <p><?php echo Text::_('COM_TAGIMPORT_IMPORT_INFO'); ?></p>
        
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <!-- Status Card -->
        <div class="card">
            <h2><?php echo Text::_('COM_TAGIMPORT_IMPORT_STATUS'); ?></h2>
            <div class="status-grid">
                <div class="status-item">
                    <div class="status-number"><?php echo $tagStatus['total_tags']; ?></div>
                    <div><?php echo Text::_('COM_TAGIMPORT_TOTAL_TAGS'); ?></div>
                </div>
                <div class="status-item">
                    <div class="status-number"><?php echo $tagStatus['imported_tags']; ?></div>
                    <div><?php echo Text::_('COM_TAGIMPORT_IMPORTED_TAGS'); ?></div>
                </div>
                <div class="status-item">
                    <div class="status-number"><?php echo $tagStatus['tracking_table_exists'] ? Text::_('JYES') : Text::_('JNO'); ?></div>
                    <div><?php echo Text::_('COM_TAGIMPORT_TRACKING_ENABLED'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Import Warning -->
        <div class="alert alert-warning">
            <?php echo Text::_('COM_TAGIMPORT_IMPORT_WARNING'); ?>
        </div>
        
        <!-- Upload Form -->
        <div class="card">
            <h2><?php echo Text::_('COM_TAGIMPORT_IMPORT_TAGS'); ?></h2>
            <form action="" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="jsonfile"><?php echo Text::_('COM_TAGIMPORT_FIELD_FILE_LABEL'); ?></label>
                    <input type="file" name="jsonfile" id="jsonfile" accept=".json" required>
                    <small><?php echo Text::_('COM_TAGIMPORT_FIELD_FILE_DESC'); ?></small>
                </div>
                <button type="submit" class="btn btn-primary"><?php echo Text::_('COM_TAGIMPORT_UPLOAD_FILE'); ?></button>
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="<?php echo Factory::getApplication()->getFormToken(); ?>" value="1">
            </form>
        </div>
        
        <!-- Preview and Import -->
        <?php if (!empty($previewData)): ?>
        <div class="card">
            <h2><?php echo Text::_('COM_TAGIMPORT_PREVIEW_IMPORT'); ?></h2>
            <p><?php echo sprintf(Text::_('COM_TAGIMPORT_PREVIEW_DESCRIPTION'), count($previewData)); ?></p>
            
            <div class="alert alert-info">
                <?php echo Text::_('COM_TAGIMPORT_PREVIEW_WARNING'); ?>
            </div>
            
            <!-- Preview Table -->
            <table class="preview-table">
                <thead>
                    <tr>
                        <th><?php echo Text::_('COM_TAGIMPORT_FIELD_TITLE'); ?></th>
                        <th><?php echo Text::_('COM_TAGIMPORT_FIELD_ALIAS'); ?></th>
                        <th><?php echo Text::_('COM_TAGIMPORT_FIELD_DESCRIPTION'); ?></th>
                        <th><?php echo Text::_('COM_TAGIMPORT_FIELD_PUBLISHED'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $previewCount = min(10, count($previewData));
                    for ($i = 0; $i < $previewCount; $i++): 
                        $tag = $previewData[$i];
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($tag['title'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($tag['alias'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars(substr($tag['description'] ?? '', 0, 100)); ?><?php echo strlen($tag['description'] ?? '') > 100 ? '...' : ''; ?></td>
                        <td><?php echo isset($tag['published']) && $tag['published'] ? Text::_('JYES') : Text::_('JNO'); ?></td>
                    </tr>
                    <?php endfor; ?>
                    <?php if (count($previewData) > 10): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; font-style: italic;">
                            <?php echo sprintf(Text::_('COM_TAGIMPORT_MORE_ITEMS'), count($previewData) - 10); ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Import and Clear buttons -->
            <form action="" method="post" style="display: inline;">
                <button type="submit" class="btn btn-success"><?php echo Text::_('COM_TAGIMPORT_IMPORT_NOW'); ?></button>
                <input type="hidden" name="action" value="import">
                <input type="hidden" name="<?php echo Factory::getApplication()->getFormToken(); ?>" value="1">
            </form>
            
            <form action="" method="post" style="display: inline;">
                <button type="submit" class="btn btn-secondary"><?php echo Text::_('COM_TAGIMPORT_CLEAR_PREVIEW'); ?></button>
                <input type="hidden" name="action" value="clear">
                <input type="hidden" name="<?php echo Factory::getApplication()->getFormToken(); ?>" value="1">
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Reset Section -->
        <?php if ($tagStatus['imported_tags'] > 0): ?>
        <div class="card">
            <h2><?php echo Text::_('COM_TAGIMPORT_RESET_IMPORTED'); ?></h2>
            <p><?php echo Text::_('COM_TAGIMPORT_RESET_INFO'); ?></p>
            
            <form action="" method="post" onsubmit="return confirm('<?php echo Text::_('COM_TAGIMPORT_RESET_CONFIRM'); ?>');">
                <button type="submit" class="btn btn-danger"><?php echo Text::_('COM_TAGIMPORT_RESET'); ?></button>
                <input type="hidden" name="action" value="reset">
                <input type="hidden" name="<?php echo Factory::getApplication()->getFormToken(); ?>" value="1">
            </form>
        </div>
        <?php endif; ?>
        
        <!-- JSON Format Help -->
        <div class="card">
            <h2><?php echo Text::_('COM_TAGIMPORT_EXPECTED_JSON_FORMAT'); ?></h2>
            <div class="json-format">
                <pre>[
  {
    "title": "Tag Title",
    "alias": "tag-alias",
    "description": "Tag description",
    "published": 1,
    "access": 1,
    "language": "*",
    "note": "Optional note",
    "metadesc": "Meta description",
    "metakey": "meta keywords"
  }
]</pre>
            </div>
        </div>
    </div>
</body>
</html>
