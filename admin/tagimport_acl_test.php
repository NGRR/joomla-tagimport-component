<?php
/**
 * Step-by-step ACL testing to find exact failure point
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;

echo "<h1>ACL Step-by-Step Testing</h1>";

$user = Factory::getUser();
echo "<p><strong>User:</strong> " . $user->username . " (ID: " . $user->id . ")</p>";

// Test 1: Basic user check (this should work)
echo "<h2>Test 1: Basic User Check</h2>";
try {
    if ($user->id > 0) {
        echo "✅ User is logged in<br>";
    } else {
        echo "❌ User is not logged in<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 2: Check if user is Super Admin (this should work)
echo "<h2>Test 2: Super Admin Check</h2>";
try {
    if (in_array(8, $user->groups)) {
        echo "✅ User is Super Admin<br>";
    } else {
        echo "❌ User is not Super Admin<br>";
        echo "User groups: " . implode(', ', $user->groups) . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 3: Try to check core.admin permission (this might fail)
echo "<h2>Test 3: Core Admin Permission</h2>";
try {
    $canAdmin = $user->authorise('core.admin');
    if ($canAdmin) {
        echo "✅ User has core.admin permission<br>";
    } else {
        echo "❌ User does not have core.admin permission<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 4: Try to check permission for our component (this might fail)
echo "<h2>Test 4: Component Permission</h2>";
try {
    $canManage = $user->authorise('core.manage', 'com_categoryimport');
    if ($canManage) {
        echo "✅ User can manage com_categoryimport<br>";
    } else {
        echo "❌ User cannot manage com_categoryimport<br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking com_categoryimport: " . $e->getMessage() . "<br>";
}

// Test 5: Try to check permission for com_categories (this will likely fail)
echo "<h2>Test 5: com_categories Permission (Expected to Fail)</h2>";
try {
    $canManageCategories = $user->authorise('core.manage', 'com_categories');
    if ($canManageCategories) {
        echo "✅ User can manage com_categories<br>";
    } else {
        echo "❌ User cannot manage com_categories<br>";
    }
} catch (Exception $e) {
    echo "❌ ERROR with com_categories: " . $e->getMessage() . "<br>";
    echo "<strong>^ This is likely our problem!</strong><br>";
}

// Test 6: Check what components exist in the database
echo "<h2>Test 6: Check Component Registration</h2>";
try {
    $db = Factory::getDbo();
    $query = $db->getQuery(true)
        ->select('element, name, enabled')
        ->from('#__extensions')
        ->where('type = ' . $db->quote('component'))
        ->where('element LIKE ' . $db->quote('%categor%'));
    
    $db->setQuery($query);
    $components = $db->loadObjectList();
    
    echo "<strong>Category-related components:</strong><br>";
    foreach ($components as $comp) {
        echo "- " . $comp->element . " (" . $comp->name . ") - Enabled: " . ($comp->enabled ? 'Yes' : 'No') . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<p><strong>Summary:</strong> Look for the first test that fails - that's where the ACL issue begins!</p>";
?>
