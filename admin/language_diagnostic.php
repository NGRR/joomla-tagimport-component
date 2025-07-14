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

// ACL check
$user = Factory::getUser();
if (!$user->authorise('core.manage', 'com_categoryimport')) {
    throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
}

$currentLang = CategoryImportLanguageHelper::getCurrentLanguage();
$availableLangs = CategoryImportLanguageHelper::getAvailableLanguages();
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo Text::_('COM_CATEGORYIMPORT'); ?> - <?php echo Text::_('COM_CATEGORYIMPORT_LANGUAGE_DIAGNOSTIC'); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .diagnostic-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .diagnostic-table th, .diagnostic-table td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        .diagnostic-table th { background-color: #f2f2f2; }
        .status-ok { color: #28a745; font-weight: bold; }
        .status-warning { color: #ffc107; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 <?php echo Text::_('COM_CATEGORYIMPORT'); ?> - Diagnóstico de Idiomas</h1>
        
        <div class="alert alert-info">
            <h3>ℹ️ Información del Sistema de Idiomas</h3>
            <p>Esta página le ayuda a diagnosticar problemas con la configuración de idiomas del componente.</p>
        </div>

        <h2>📊 Estado Actual del Sistema</h2>
        
        <table class="diagnostic-table">
            <tr>
                <th>Elemento</th>
                <th>Estado</th>
                <th>Valor</th>
                <th>Observaciones</th>
            </tr>
            <tr>
                <td>Idioma Actual del Sistema</td>
                <td><span class="status-ok">✓ OK</span></td>
                <td><strong><?php echo $currentLang; ?></strong></td>
                <td>Idioma configurado en Joomla</td>
            </tr>
            <tr>
                <td>Archivo de Idioma Principal</td>
                <td>
                    <?php 
                    $mainFile = JPATH_ADMINISTRATOR . '/components/com_categoryimport/language/' . $currentLang . '/com_categoryimport.ini';
                    if (file_exists($mainFile)): ?>
                        <span class="status-ok">✓ EXISTE</span>
                    <?php else: ?>
                        <span class="status-error">✗ NO EXISTE</span>
                    <?php endif; ?>
                </td>
                <td><?php echo $mainFile; ?></td>
                <td>Archivo principal de traducciones</td>
            </tr>
            <tr>
                <td>Archivo de Idioma del Sistema</td>
                <td>
                    <?php 
                    $sysFile = JPATH_ADMINISTRATOR . '/components/com_categoryimport/language/' . $currentLang . '/com_categoryimport.sys.ini';
                    if (file_exists($sysFile)): ?>
                        <span class="status-ok">✓ EXISTE</span>
                    <?php else: ?>
                        <span class="status-error">✗ NO EXISTE</span>
                    <?php endif; ?>
                </td>
                <td><?php echo $sysFile; ?></td>
                <td>Archivo para el sistema Joomla</td>
            </tr>
        </table>

        <h2>🌐 Idiomas Disponibles</h2>
        
        <table class="diagnostic-table">
            <tr>
                <th>Código de Idioma</th>
                <th>Estado</th>
                <th>Archivo Principal</th>
                <th>Archivo Sistema</th>
            </tr>
            <?php foreach ($availableLangs as $lang): ?>
            <tr>
                <td><strong><?php echo $lang; ?></strong> <?php echo ($lang === $currentLang) ? '(ACTUAL)' : ''; ?></td>
                <td>
                    <?php if (CategoryImportLanguageHelper::isLanguageAvailable($lang)): ?>
                        <span class="status-ok">✓ DISPONIBLE</span>
                    <?php else: ?>
                        <span class="status-warning">⚠ INCOMPLETO</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php 
                    $mainFile = JPATH_ADMINISTRATOR . '/components/com_categoryimport/language/' . $lang . '/com_categoryimport.ini';
                    echo file_exists($mainFile) ? '✓' : '✗';
                    ?>
                </td>
                <td>
                    <?php 
                    $sysFile = JPATH_ADMINISTRATOR . '/components/com_categoryimport/language/' . $lang . '/com_categoryimport.sys.ini';
                    echo file_exists($sysFile) ? '✓' : '✗';
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <h2>🧪 Prueba de Traducciones</h2>
        
        <table class="diagnostic-table">
            <tr>
                <th>Clave de Traducción</th>
                <th>Texto Traducido</th>
                <th>Estado</th>
            </tr>
            <?php 
            $testKeys = [
                'COM_CATEGORYIMPORT',
                'COM_CATEGORYIMPORT_XML_DESCRIPTION',
                'COM_CATEGORYIMPORT_TITLE',
                'COM_CATEGORYIMPORT_IMPORT_CATEGORIES',
                'COM_CATEGORYIMPORT_READY'
            ];
            
            foreach ($testKeys as $key): 
                $translation = Text::_($key);
                $isTranslated = ($translation !== $key);
            ?>
            <tr>
                <td><code><?php echo $key; ?></code></td>
                <td><?php echo htmlentities($translation); ?></td>
                <td>
                    <?php if ($isTranslated): ?>
                        <span class="status-ok">✓ TRADUCIDO</span>
                    <?php else: ?>
                        <span class="status-error">✗ SIN TRADUCIR</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <div class="alert alert-success">
            <h3>✅ Recomendaciones</h3>
            <ul>
                <li>Si ve "✗ SIN TRADUCIR" en las pruebas, verifique que los archivos de idioma contengan todas las claves necesarias.</li>
                <li>Si su idioma actual no aparece como disponible, asegúrese de que los archivos .ini existan en la carpeta correcta.</li>
                <li>Después de hacer cambios, es recomendable limpiar la caché de Joomla.</li>
                <li>Si el problema persiste, verifique los permisos de archivos y carpetas.</li>
            </ul>
        </div>
        
        <p><a href="categoryimport.php">« Volver al Componente Principal</a></p>
    </div>
</body>
</html>
