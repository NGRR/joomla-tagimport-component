<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_categoryimport
 *
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

// Load language helper
require_once JPATH_COMPONENT . '/helpers/language.php';
CategoryImportLanguageHelper::loadLanguage();

// Force language reload
$lang = Factory::getLanguage();
$lang->load('com_categoryimport', JPATH_ADMINISTRATOR, null, true, true);
$lang->load('com_categoryimport.sys', JPATH_ADMINISTRATOR, null, true, true);

// ACL check
$user = Factory::getUser();
if (!$user->authorise('core.manage', 'com_categoryimport')) {
    throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
}

// Clear language cache if it exists
$cache = Factory::getCache('_system');
if ($cache) {
    $cache->clean('com_categoryimport');
}

// Clear component cache
$cache = Factory::getCache('com_categoryimport');
if ($cache) {
    $cache->clean();
}

Factory::getApplication()->enqueueMessage('Cache limpiado y idiomas recargados.', 'success');
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo Text::_('COM_CATEGORYIMPORT'); ?> - Cache Cleanup</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧹 <?php echo Text::_('COM_CATEGORYIMPORT'); ?> - Limpieza de Cache</h1>
        
        <div class="alert alert-success">
            <h3>✅ Cache Limpiado</h3>
            <p>Los archivos de cache y idiomas han sido recargados. El componente debería mostrar ahora los textos en el idioma correcto.</p>
        </div>

        <div class="alert alert-info">
            <h3>🔄 Verificación de Idiomas</h3>
            <p><strong>Idioma actual:</strong> <?php echo CategoryImportLanguageHelper::getCurrentLanguage(); ?></p>
            <p><strong>Prueba de traducción:</strong></p>
            <ul>
                <li><code>COM_CATEGORYIMPORT</code>: <?php echo Text::_('COM_CATEGORYIMPORT'); ?></li>
                <li><code>COM_CATEGORYIMPORT_COMPONENT_TITLE</code>: <?php echo Text::_('COM_CATEGORYIMPORT_COMPONENT_TITLE'); ?></li>
                <li><code>COM_CATEGORYIMPORT_COMPONENT_WORKING</code>: <?php echo Text::_('COM_CATEGORYIMPORT_COMPONENT_WORKING'); ?></li>
            </ul>
        </div>
        
        <p><a href="categoryimport.php">« Volver al Componente Principal</a></p>
        <p><a href="language_diagnostic.php">🔍 Ejecutar Diagnóstico de Idiomas</a></p>
    </div>
</body>
</html>
