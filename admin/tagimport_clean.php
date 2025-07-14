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

$debugMode = $params->get('debug_mode', 'default');

/**
 * Get tag import status from database
 *
 * @return array Status information
 */
function getTagImportStatus()
{
    $db = Factory::getDbo();
    $status = [
        'total_tags' => 0,
        'recent_imports' => 0,
        'tracking_table_exists' => false,
        'last_import_date' => null,
        'controller_executed' => false
    ];
    
    try {
        // Check if tracking table exists
        $tables = $db->getTableList();
        $prefix = $db->getPrefix();
        $trackingTable = $prefix . 'tagimport_tracking';
        
        if (in_array($trackingTable, $tables)) {
            $status['tracking_table_exists'] = true;
            
            // Get count of tracked imports
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from('#__tagimport_tracking');
            $db->setQuery($query);
            $status['recent_imports'] = (int) $db->loadResult();
            
            // Get last import date
            if ($status['recent_imports'] > 0) {
                $query = $db->getQuery(true)
                    ->select('MAX(imported_date)')
                    ->from('#__tagimport_tracking');
                $db->setQuery($query);
                $status['last_import_date'] = $db->loadResult();
                $status['controller_executed'] = true;
            }
        }
        
        // Get total tags count
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__tags')
            ->where('id > 1'); // Exclude root tag
        $db->setQuery($query);
        $status['total_tags'] = (int) $db->loadResult();
        
    } catch (Exception $e) {
        $status['error'] = $e->getMessage();
    }
    
    return $status;
}

$tagStatus = getTagImportStatus();

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $app->getFormToken();
    if (isset($_POST[$token]) && $_POST[$token] === '1') {
        // Process file upload
        if (isset($_FILES['jsonfile']) && $_FILES['jsonfile']['error'] === 0) {
            $uploadedFile = $_FILES['jsonfile'];
            $fileName = $uploadedFile['name'];
            $tmpName = $uploadedFile['tmp_name'];
            
            // Validate file type
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if ($fileExtension === 'json') {
                // Read and validate JSON
                $jsonContent = file_get_contents($tmpName);
                $data = json_decode($jsonContent, true);
                
                if ($data !== null) {
                    $message = 'JSON file uploaded successfully. Contains ' . count($data) . ' items.';
                    $messageType = 'success';
                    
                    // TODO: Process the tag import here
                    // This is where you would implement the actual tag import logic
                    
                } else {
                    $message = 'Invalid JSON file format.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Please upload a valid JSON file.';
                $messageType = 'error';
            }
        } else {
            $message = 'No file uploaded or upload error occurred.';
            $messageType = 'error';
        }
    } else {
        $message = 'Invalid security token.';
        $messageType = 'error';
    }
}

// Load Joomla's CSS framework
HTMLHelper::_('bootstrap.framework');
HTMLHelper::_('formbehavior.chosen');

?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo Text::_('COM_TAGIMPORT'); ?> - Tag Import Interface</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin: 15px 0; }
        .form-control { width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background-color: #007cba; color: white; }
        .btn-primary:hover { background-color: #005a87; }
        .debug-info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #007cba; }
        .upload-area { border: 2px dashed #ccc; padding: 40px; text-align: center; border-radius: 10px; margin: 20px 0; }
        .upload-area.dragover { border-color: #007cba; background-color: #f0f8ff; }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo Text::_('COM_TAGIMPORT'); ?></h1>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <div class="debug-info">
            <strong>Debug Mode:</strong> <?php echo htmlspecialchars($debugMode); ?><br>
            <strong>User:</strong> <?php echo htmlspecialchars($user->name); ?> (ID: <?php echo $user->id; ?>)<br>
            <strong>Permissions:</strong> <?php echo $user->authorise('core.manage', 'com_tagimport') ? 'Authorized' : 'Not Authorized'; ?>
        </div>
        
        <!-- Database Status Box -->
        <div class="alert alert-<?php echo $tagStatus['controller_executed'] ? 'success' : 'info'; ?>">
            <h4>📊 Database Status</h4>
            <strong>Controller Status:</strong> 
            <?php if ($tagStatus['controller_executed']): ?>
                ✅ <span style="color: green;">Controller has executed successfully</span>
            <?php else: ?>
                ⏳ <span style="color: orange;">Controller not executed yet (upload a file to test)</span>
            <?php endif; ?>
            <br>
            
            <strong>Total Tags in Database:</strong> <?php echo $tagStatus['total_tags']; ?><br>
            
            <?php if ($tagStatus['tracking_table_exists']): ?>
                <strong>Tracking Table:</strong> ✅ Exists<br>
                <strong>Imported Tags Tracked:</strong> <?php echo $tagStatus['recent_imports']; ?><br>
                <?php if ($tagStatus['last_import_date']): ?>
                    <strong>Last Import:</strong> <?php echo $tagStatus['last_import_date']; ?><br>
                <?php endif; ?>
            <?php else: ?>
                <strong>Tracking Table:</strong> ❌ Not created yet<br>
            <?php endif; ?>
            
            <?php if (isset($tagStatus['error'])): ?>
                <strong>Error:</strong> <span style="color: red;"><?php echo htmlspecialchars($tagStatus['error']); ?></span><br>
            <?php endif; ?>
        </div>
        
        <!-- Test File Information -->
        <div class="debug-info">
            <h4>📁 Test File Available</h4>
            <p><strong>Ready-to-use test file:</strong> <code>test_tags.json</code></p>
            <p>This file contains 8 sample tags with proper structure for testing the import functionality.</p>
            <p><strong>Location:</strong> <code>c:\Proyectos\modulos\com_tag_import\test_tags.json</code></p>
            <p><strong>Content:</strong> Technology-related tags with hierarchical structure (Technology → Programming → PHP, JavaScript, etc.)</p>
        </div>
        
        <form method="post" enctype="multipart/form-data" class="upload-form">
            <?php echo HTMLHelper::_('form.token'); ?>
            
            <div class="upload-area" id="uploadArea">
                <div class="form-group">
                    <label for="jsonfile"><strong>Select JSON file with tags:</strong></label>
                    <input type="file" 
                           name="jsonfile" 
                           id="jsonfile" 
                           accept=".json" 
                           class="form-control" 
                           required>
                </div>
                <p>Or drag and drop a JSON file here</p>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    Upload and Process Tags
                </button>
            </div>
        </form>
        
        <div class="debug-info">
            <h4>Expected JSON Format</h4>
            <p>Your JSON file should contain an array of tag objects or a "value" property with the array:</p>
            <pre>{
  "value": [
    {
      "title": "Tag Name",
      "alias": "tag-alias",
      "description": "Tag description",
      "published": 1,
      "access": 1,
      "language": "*",
      "parent_id": 1
    }
  ]
}</pre>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('jsonfile');
        
        // Drag and drop functionality
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
            }
        });
    });
    </script>
</body>
</html>
