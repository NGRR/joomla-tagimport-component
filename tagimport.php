<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_tagimport
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

// Cargar archivos de idioma directamente sin helper
$app = Factory::getApplication();
$lang = $app->getLanguage();
$lang->load('com_tagimport', JPATH_ADMINISTRATOR);

// Obtener configuración del componente
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

// Mostrar mensaje de depuración
$app->enqueueMessage('Componente com_tagimport cargado correctamente - Modo: ' . $debugMode, 'info');

// Cargar el controlador apropiado según el modo de depuración
switch ($debugMode) {
    case 'proper':
        if (file_exists(__DIR__ . '/admin/tagimport_proper.php')) {
            require_once __DIR__ . '/admin/tagimport_proper.php';
        } else {
            $app->enqueueMessage('Archivo admin/tagimport_proper.php no encontrado', 'warning');
            require_once __DIR__ . '/admin/views/tagimport/view.html.php';
        }
        break;
    case 'acl_test':
        if (file_exists(__DIR__ . '/admin/tagimport_acl_test.php')) {
            require_once __DIR__ . '/admin/tagimport_acl_test.php';
        } else {
            $app->enqueueMessage('Archivo admin/tagimport_acl_test.php no encontrado', 'warning');
            require_once __DIR__ . '/admin/views/tagimport/view.html.php';
        }
        break;
    case 'ultra':
        if (file_exists(__DIR__ . '/admin/tagimport_test_ultra.php')) {
            require_once __DIR__ . '/admin/tagimport_test_ultra.php';
        } else {
            $app->enqueueMessage('Archivo admin/tagimport_test_ultra.php no encontrado', 'warning');
            require_once __DIR__ . '/admin/views/tagimport/view.html.php';
        }
        break;
    case 'minimal':
        if (file_exists(__DIR__ . '/admin/tagimport_ultra_minimal.php')) {
            require_once __DIR__ . '/admin/tagimport_ultra_minimal.php';
        } else {
            $app->enqueueMessage('Archivo admin/tagimport_ultra_minimal.php no encontrado', 'warning');
            require_once __DIR__ . '/admin/views/tagimport/view.html.php';
        }
        break;
    default:
        // Cargar la vista predeterminada
        if (file_exists(__DIR__ . '/admin/views/tagimport/view.html.php')) {
            require_once __DIR__ . '/admin/views/tagimport/view.html.php';
        } else {
            $app->enqueueMessage('Vista predeterminada no encontrada', 'warning');
            // Como fallback, cargar el archivo tagimport.php de admin
            if (file_exists(__DIR__ . '/admin/tagimport.php')) {
                require_once __DIR__ . '/admin/tagimport.php';
            } else {
                echo '<h2>Componente Tag Import</h2>';
                echo '<p>El componente está cargado correctamente. Modo de depuración: ' . $debugMode . '</p>';
            }
        }
        break;
}