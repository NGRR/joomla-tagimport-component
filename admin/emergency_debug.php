<?php
/**
 * Emergency ACL Debug - Direct DB Check
 * Access this file via: http://localhost:8080/administrator/components/com_categoryimport/emergency_debug.php
 */

// Initialize Joomla minimal
define('_JEXEC', 1);
define('JPATH_BASE', realpath(dirname(__FILE__) . '/../../../../../../'));
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

$app = Factory::getApplication('administrator');

echo '<html><head><title>Emergency ACL Debug</title></head><body>';
echo '<h1>Emergency ACL Debug - Deep Analysis</h1>';

echo '<div style="background: #f0f0f0; padding: 15px; margin: 10px 0; border: 1px solid #ccc;">';
echo '<h2>1. Database Connection Test</h2>';

try {
    $db = Factory::getDbo();
    echo '<p>✅ Database connection: SUCCESS</p>';
    echo '<p><strong>Database type:</strong> ' . get_class($db) . '</p>';
    
    // Test basic query
    $query = $db->getQuery(true)->select('COUNT(*)')->from('#__extensions');
    $db->setQuery($query);
    $count = $db->loadResult();
    echo '<p><strong>Extensions count:</strong> ' . $count . '</p>';
    
} catch (Exception $e) {
    echo '<p>❌ Database error: ' . $e->getMessage() . '</p>';
}

echo '</div>';

echo '<div style="background: #e0f0e0; padding: 15px; margin: 10px 0; border: 1px solid #ccc;">';
echo '<h2>2. Components Check in Database</h2>';

try {
    $db = Factory::getDbo();
    
    // Check com_categories in extensions table
    $query = $db->getQuery(true)
        ->select('*')
        ->from('#__extensions')
        ->where('element = ' . $db->quote('com_categories'))
        ->where('type = ' . $db->quote('component'));
    
    $db->setQuery($query);
    $categoriesExt = $db->loadObject();
    
    echo '<p><strong>com_categories in #__extensions:</strong> ' . ($categoriesExt ? 'EXISTS (ID: ' . $categoriesExt->extension_id . ')' : 'NOT FOUND') . '</p>';
    
    if ($categoriesExt) {
        echo '<p><strong>Enabled:</strong> ' . ($categoriesExt->enabled ? 'YES' : 'NO') . '</p>';
        echo '<p><strong>Manifest cache:</strong> ' . (strlen($categoriesExt->manifest_cache) > 0 ? 'EXISTS' : 'EMPTY') . '</p>';
    }
    
    // Check com_categoryimport in extensions table
    $query = $db->getQuery(true)
        ->select('*')
        ->from('#__extensions')
        ->where('element = ' . $db->quote('com_categoryimport'))
        ->where('type = ' . $db->quote('component'));
    
    $db->setQuery($query);
    $ourExt = $db->loadObject();
    
    echo '<p><strong>com_categoryimport in #__extensions:</strong> ' . ($ourExt ? 'EXISTS (ID: ' . $ourExt->extension_id . ')' : 'NOT FOUND') . '</p>';
    
    if ($ourExt) {
        echo '<p><strong>Enabled:</strong> ' . ($ourExt->enabled ? 'YES' : 'NO') . '</p>';
        echo '<p><strong>Manifest cache:</strong> ' . (strlen($ourExt->manifest_cache) > 0 ? 'EXISTS' : 'EMPTY') . '</p>';
    }
    
} catch (Exception $e) {
    echo '<p>❌ Database query error: ' . $e->getMessage() . '</p>';
}

echo '</div>';

echo '<div style="background: #f0e0e0; padding: 15px; margin: 10px 0; border: 1px solid #ccc;">';
echo '<h2>3. File System Check</h2>';

$componentsDir = JPATH_ADMINISTRATOR . '/components';
echo '<p><strong>Components directory:</strong> ' . $componentsDir . '</p>';
echo '<p><strong>Directory exists:</strong> ' . (is_dir($componentsDir) ? 'YES' : 'NO') . '</p>';

if (is_dir($componentsDir)) {
    $components = glob($componentsDir . '/com_*', GLOB_ONLYDIR);
    echo '<p><strong>Components found:</strong> ' . count($components) . '</p>';
    
    // Check specific components
    $categoriesDir = $componentsDir . '/com_categories';
    $ourDir = $componentsDir . '/com_categoryimport';
    
    echo '<p><strong>com_categories directory:</strong> ' . (is_dir($categoriesDir) ? 'EXISTS' : 'MISSING') . '</p>';
    echo '<p><strong>com_categoryimport directory:</strong> ' . (is_dir($ourDir) ? 'EXISTS' : 'MISSING') . '</p>';
    
    // Check access files
    if (is_dir($categoriesDir)) {
        $catAccessFile = $categoriesDir . '/access.xml';
        echo '<p><strong>com_categories access.xml:</strong> ' . (file_exists($catAccessFile) ? 'EXISTS' : 'MISSING') . '</p>';
        if (file_exists($catAccessFile)) {
            echo '<p><strong>Size:</strong> ' . filesize($catAccessFile) . ' bytes</p>';
            echo '<p><strong>Readable:</strong> ' . (is_readable($catAccessFile) ? 'YES' : 'NO') . '</p>';
            
            // Try to read first few lines
            $content = file_get_contents($catAccessFile, false, null, 0, 200);
            echo '<p><strong>Content preview:</strong><br><code>' . htmlspecialchars($content) . '</code></p>';
        }
    }
    
    if (is_dir($ourDir)) {
        $ourAccessFile = $ourDir . '/access.xml';
        echo '<p><strong>com_categoryimport access.xml:</strong> ' . (file_exists($ourAccessFile) ? 'EXISTS' : 'MISSING') . '</p>';
    }
}

echo '</div>';

echo '<div style="background: #e0e0f0; padding: 15px; margin: 10px 0; border: 1px solid #ccc;">';
echo '<h2>4. Error Source Investigation</h2>';

echo '<p><strong>PHP Error Reporting:</strong> ' . error_reporting() . '</p>';
echo '<p><strong>Display Errors:</strong> ' . ini_get('display_errors') . '</p>';

// Try to trigger the exact same code path that's causing issues
echo '<p><strong>Testing ComponentHelper::getComponent()...</strong></p>';

try {
    $categoriesComponent = \Joomla\CMS\Component\ComponentHelper::getComponent('com_categories');
    echo '<p>✅ ComponentHelper::getComponent(com_categories): SUCCESS</p>';
    echo '<p><strong>Component ID:</strong> ' . $categoriesComponent->id . '</p>';
} catch (Exception $e) {
    echo '<p>❌ ComponentHelper::getComponent(com_categories): ' . $e->getMessage() . '</p>';
}

try {
    $ourComponent = \Joomla\CMS\Component\ComponentHelper::getComponent('com_categoryimport');
    echo '<p>✅ ComponentHelper::getComponent(com_categoryimport): SUCCESS</p>';
    echo '<p><strong>Component ID:</strong> ' . $ourComponent->id . '</p>';
} catch (Exception $e) {
    echo '<p>❌ ComponentHelper::getComponent(com_categoryimport): ' . $e->getMessage() . '</p>';
}

echo '</div>';

echo '<div style="background: #ffe0e0; padding: 15px; margin: 10px 0; border: 1px solid #ccc;">';
echo '<h2>5. ACL System Test</h2>';

echo '<p><strong>Testing ACL functions...</strong></p>';

try {
    $user = Factory::getUser();
    echo '<p>✅ Factory::getUser(): SUCCESS</p>';
    echo '<p><strong>User ID:</strong> ' . $user->id . '</p>';
    echo '<p><strong>Is Super Admin:</strong> ' . ($user->authorise('core.admin') ? 'YES' : 'NO') . '</p>';
    
    // Test authorization for different components
    echo '<p><strong>Testing authorizations:</strong></p>';
    
    $permissions = [
        'core.admin' => 'Super Admin',
        'core.manage' => 'Global Manage',
        'core.manage/com_categories' => 'Manage Categories',
        'core.manage/com_categoryimport' => 'Manage Our Component'
    ];
    
    foreach ($permissions as $permission => $label) {
        $parts = explode('/', $permission);
        $action = $parts[0];
        $asset = $parts[1] ?? null;
        
        $result = $user->authorise($action, $asset);
        echo '<p>• ' . $label . ': ' . ($result ? '✅ ALLOWED' : '❌ DENIED') . '</p>';
    }
    
} catch (Exception $e) {
    echo '<p>❌ ACL test error: ' . $e->getMessage() . '</p>';
    echo '<p><strong>Stack trace:</strong><br><pre>' . $e->getTraceAsString() . '</pre></p>';
}

echo '</div>';

echo '<p><strong>Debug completed. Review all sections for issues.</strong></p>';
echo '</body></html>';
