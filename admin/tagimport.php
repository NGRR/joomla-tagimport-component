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
use Joomla\CMS\Table\Table as JTable;

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
 * Sort tags by hierarchy to ensure parents are processed before children
 */
function sortTagsByHierarchy($data)
{
    $sorted = [];
    $processed = [];
    $rootTags = [];
    $childTags = [];
    
    // Separate root tags from child tags
    foreach ($data as $tag) {
        if (!isset($tag['parent_alias']) || empty($tag['parent_alias'])) {
            $rootTags[] = $tag;
        } else {
            $childTags[] = $tag;
        }
    }
    
    // Add root tags first
    foreach ($rootTags as $tag) {
        $sorted[] = $tag;
        if (isset($tag['alias'])) {
            $processed[$tag['alias']] = true;
        }
    }
    
    // Process child tags in multiple passes until all are processed
    $maxPasses = 10; // Prevent infinite loops
    $pass = 0;
    
    while (!empty($childTags) && $pass < $maxPasses) {
        $remainingTags = [];
        
        foreach ($childTags as $tag) {
            if (isset($tag['parent_alias']) && isset($processed[$tag['parent_alias']])) {
                // Parent already processed, can add this tag now
                $sorted[] = $tag;
                if (isset($tag['alias'])) {
                    $processed[$tag['alias']] = true;
                }
            } else {
                // Parent not yet processed, keep for next pass
                $remainingTags[] = $tag;
            }
        }
        
        $childTags = $remainingTags;
        $pass++;
    }
    
    // Add any remaining tags (orphaned children) at the end
    foreach ($childTags as $tag) {
        $sorted[] = $tag;
    }
    
    return $sorted;
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
function processTagImport($data, $globalParentId = 1)
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
        
        // Sort tags by hierarchy: process parents before children
        $sortedData = sortTagsByHierarchy($data);
        Log::add('Tags ordenados por jerarquía para procesamiento correcto', Log::DEBUG, 'com_tagimport');
        
        foreach ($sortedData as $tagData) {
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
                
                // Handle hierarchy fields
                $parentId = isset($tagData['parent_id']) ? (int)$tagData['parent_id'] : (int)$globalParentId;
                $level = isset($tagData['level']) ? (int)$tagData['level'] : 1;
                $path = isset($tagData['path']) ? $tagData['path'] : $alias;
                
                Log::add('Procesando tag "' . $title . '" - parent_id inicial: ' . $parentId . ' (global_parent: ' . $globalParentId . ')', Log::DEBUG, 'com_tagimport');
                
                // If parent_id references another tag by alias, resolve it
                if (isset($tagData['parent_alias'])) {
                    Log::add('Intentando resolver parent_alias "' . $tagData['parent_alias'] . '" para tag "' . $title . '"', Log::DEBUG, 'com_tagimport');
                    
                    $parentQuery = $db->getQuery(true)
                        ->select('id')
                        ->from('#__tags')
                        ->where('alias = ' . $db->quote($tagData['parent_alias']));
                    $db->setQuery($parentQuery);
                    $resolvedParentId = $db->loadResult();
                    
                    if ($resolvedParentId) {
                        $parentId = $resolvedParentId;
                        Log::add('Parent ID resuelto para "' . $alias . '": parent "' . $tagData['parent_alias'] . '" = ID ' . $parentId, Log::INFO, 'com_tagimport');
                    } else {
                        Log::add('ADVERTENCIA: No se encontró parent con alias "' . $tagData['parent_alias'] . '" para tag "' . $title . '". Usando global_parent_id: ' . $globalParentId, Log::WARNING, 'com_tagimport');
                        $parentId = (int)$globalParentId;
                    }
                } else {
                    Log::add('Tag "' . $title . '" usa parent_id global: ' . $parentId, Log::DEBUG, 'com_tagimport');
                }
                
                $currentDate = Factory::getDate()->toSql();
                
                // Use Joomla's TagTable for proper nested set handling
                JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_tags/tables');
                $table = JTable::getInstance('Tag', 'TagsTable', ['dbo' => $db]);
                
                // Prepare data array for Joomla table
                $tableData = [
                    'title' => $title,
                    'alias' => $alias,
                    'description' => $description,
                    'published' => $published,
                    'access' => $access,
                    'language' => $language,
                    'note' => $note,
                    'metadesc' => $metadesc,
                    'metakey' => $metakey,
                    'parent_id' => $parentId,
                    'params' => '{}',
                    'metadata' => '{}',
                    'images' => '{}',
                    'urls' => '{}',
                    'hits' => 0,
                    'version' => 1,
                    'created_time' => $currentDate,
                    'created_user_id' => $user->id,
                    'modified_time' => $currentDate,
                    'modified_user_id' => $user->id,
                    'created_by_alias' => ''
                ];
                
                Log::add('Datos finales para tag "' . $title . '": parent_id=' . $parentId . ', level=' . $level . ', path=' . $path, Log::INFO, 'com_tagimport');
                
                // Bind and save using Joomla's table (handles nested set automatically)
                if ($table->bind($tableData)) {
                    Log::add('Bind exitoso para tag "' . $title . '". parent_id en tabla: ' . $table->parent_id, Log::DEBUG, 'com_tagimport');
                    
                    if ($table->check()) {
                        Log::add('Check exitoso para tag "' . $title . '". parent_id después de check: ' . $table->parent_id, Log::DEBUG, 'com_tagimport');
                        
                        if ($table->store()) {
                            $tagId = $table->id;
                            Log::add('Tag "' . $title . '" importado exitosamente con ID: ' . $tagId . ' (parent_id enviado: ' . $parentId . ', parent_id final: ' . $table->parent_id . ')', Log::INFO, 'com_tagimport');
                            
                            // Verificar parent_id en la base de datos inmediatamente después del store
                            $verifyQuery = $db->getQuery(true)
                                ->select('parent_id, level, path')
                                ->from('#__tags')
                                ->where('id = ' . (int)$tagId);
                            $db->setQuery($verifyQuery);
                            $verifyResult = $db->loadAssoc();
                            
                            Log::add('Verificación DB para tag "' . $title . '": parent_id=' . $verifyResult['parent_id'] . ', level=' . $verifyResult['level'] . ', path=' . $verifyResult['path'], Log::DEBUG, 'com_tagimport');
                            
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
                            $errorMsg = 'Error en store para tag "' . $title . '": ' . $table->getError();
                            $errors[] = $errorMsg;
                            Log::add($errorMsg, Log::ERROR, 'com_tagimport');
                            $skippedCount++;
                        }
                    } else {
                        $errorMsg = 'Error en check para tag "' . $title . '": ' . $table->getError();
                        $errors[] = $errorMsg;
                        Log::add($errorMsg, Log::ERROR, 'com_tagimport');
                        $skippedCount++;
                    }
                } else {
                    $errorMsg = 'Error en bind para tag "' . $title . '": ' . $table->getError();
                    $errors[] = $errorMsg;
                    Log::add($errorMsg, Log::ERROR, 'com_tagimport');
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
        
        // Reconstruir nested set después de importación
        if ($importedCount > 0) {
            Log::add('Reconstruyendo nested set de tags...', Log::INFO, 'com_tagimport');
            $rebuildResult = rebuildTagsNestedSet();
            if ($rebuildResult['success']) {
                Log::add('Nested set reconstruido exitosamente', Log::INFO, 'com_tagimport');
            } else {
                Log::add('Error reconstruyendo nested set: ' . $rebuildResult['error'], Log::WARNING, 'com_tagimport');
            }
        }
        
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
 * Rebuild tags nested set using Joomla's built-in nested set table class
 */
function rebuildTagsNestedSet()
{
    try {
        $db = Factory::getDbo();
        
        Log::add('Iniciando reconstrucción del nested set usando TagsTable', Log::INFO, 'com_tagimport');
        
        // Use Joomla's built-in Tags table class
        JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_tags/tables');
        $table = JTable::getInstance('Tag', 'TagsTable');
        
        if (!$table) {
            Log::add('Error: No se pudo cargar TagsTable', Log::ERROR, 'com_tagimport');
            return ['success' => false, 'error' => 'No se pudo cargar TagsTable'];
        }
        
        // First, backup the current parent_id relationships before rebuild
        Log::add('Respaldando relaciones parent_id antes de reconstrucción', Log::DEBUG, 'com_tagimport');
        
        $query = $db->getQuery(true)
            ->select('id, parent_id, title, alias')
            ->from('#__tags')
            ->where('id > 1'); // Exclude ROOT
        
        $db->setQuery($query);
        $parentRelations = $db->loadAssocList('id');
        
        Log::add('Respaldadas ' . count($parentRelations) . ' relaciones parent_id', Log::DEBUG, 'com_tagimport');
        
        // Ensure all tags have valid parent_id relationships
        Log::add('Verificando relaciones parent_id', Log::DEBUG, 'com_tagimport');
        
        foreach ($parentRelations as $tagId => $tag) {
            // Verify parent exists
            if ($tag['parent_id'] > 1) {
                $query = $db->getQuery(true)
                    ->select('id')
                    ->from('#__tags')
                    ->where('id = ' . (int)$tag['parent_id']);
                
                $db->setQuery($query);
                $parentExists = $db->loadResult();
                
                if (!$parentExists) {
                    Log::add("Warning: Parent ID {$tag['parent_id']} no existe para tag '{$tag['title']}', asignando al ROOT", Log::WARNING, 'com_tagimport');
                    
                    // Update to ROOT parent
                    $query = $db->getQuery(true)
                        ->update('#__tags')
                        ->set('parent_id = 1')
                        ->where('id = ' . (int)$tagId);
                    
                    $db->setQuery($query);
                    $db->execute();
                    
                    // Update our backup too
                    $parentRelations[$tagId]['parent_id'] = 1;
                }
            }
        }
        
        // Now rebuild the nested set using Joomla's method
        Log::add('Ejecutando rebuild del nested set', Log::DEBUG, 'com_tagimport');
        
        // Directly call the nested set rebuild
        $result = $table->rebuild(1); // 1 is the ROOT parent
        
        if ($result) {
            // After rebuild, restore the parent_id relationships if they were lost
            Log::add('Verificando y restaurando relaciones parent_id post-rebuild', Log::DEBUG, 'com_tagimport');
            
            foreach ($parentRelations as $tagId => $originalTag) {
                // Check if parent_id was preserved
                $query = $db->getQuery(true)
                    ->select('parent_id')
                    ->from('#__tags')
                    ->where('id = ' . (int)$tagId);
                
                $db->setQuery($query);
                $currentParentId = $db->loadResult();
                
                if ($currentParentId != $originalTag['parent_id']) {
                    Log::add("Restaurando parent_id para tag '{$originalTag['title']}': {$currentParentId} -> {$originalTag['parent_id']}", Log::DEBUG, 'com_tagimport');
                    
                    // Restore the original parent_id
                    $query = $db->getQuery(true)
                        ->update('#__tags')
                        ->set('parent_id = ' . (int)$originalTag['parent_id'])
                        ->where('id = ' . (int)$tagId);
                    
                    $db->setQuery($query);
                    $db->execute();
                }
            }
            
            // Run rebuild again to ensure nested set is consistent with restored parent_ids
            Log::add('Ejecutando segundo rebuild para consistencia', Log::DEBUG, 'com_tagimport');
            $table->rebuild(1);
            
            Log::add('Nested set reconstruido exitosamente usando TagsTable', Log::INFO, 'com_tagimport');
            
            // Verify the rebuild worked by checking all non-root tags
            $query = $db->getQuery(true)
                ->select('id, title, parent_id, level, lft, rgt, path')
                ->from('#__tags')
                ->where('id > 1') // Exclude ROOT
                ->order('lft ASC');
            
            $db->setQuery($query);
            $allTags = $db->loadAssocList();
            
            $hierarchicalCount = 0;
            foreach ($allTags as $tag) {
                if ($tag['parent_id'] > 1) {
                    $hierarchicalCount++;
                }
                Log::add("Tag verificado: {$tag['title']} - parent_id:{$tag['parent_id']}, level:{$tag['level']}, lft:{$tag['lft']}, rgt:{$tag['rgt']}", Log::DEBUG, 'com_tagimport');
            }
            
            Log::add("Verificación completa: " . count($allTags) . " tags totales, {$hierarchicalCount} con jerarquía", Log::INFO, 'com_tagimport');
            
            return ['success' => true];
        } else {
            Log::add('Error en rebuild del nested set', Log::ERROR, 'com_tagimport');
            return ['success' => false, 'error' => 'TagsTable::rebuild() falló'];
        }
        
    } catch (Exception $e) {
        Log::add('Error en reconstrucción del nested set: ' . $e->getMessage(), Log::ERROR, 'com_tagimport');
        return ['success' => false, 'error' => $e->getMessage()];
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
 * Show current tags status for debugging
 */
function showTagsStatus()
{
    $db = Factory::getDbo();
    
    echo "<!DOCTYPE html><html><head><title>Estado de Tags</title>";
    echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #ccc; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style>";
    echo "</head><body>";
    echo "<h1>Estado Actual de Tags</h1>\n";
    
    try {
        // Mostrar todos los tags con jerarquía
        $query = $db->getQuery(true)
            ->select('id, title, alias, parent_id, level, lft, rgt, path, published')
            ->from('#__tags')
            ->order('lft ASC');
        
        $db->setQuery($query);
        $tags = $db->loadAssocList();
        
        if ($tags) {
            echo "<table>\n";
            echo "<tr><th>ID</th><th>Title</th><th>Alias</th><th>Parent ID</th><th>Level</th><th>Lft</th><th>Rgt</th><th>Path</th><th>Published</th><th>Jerarquía</th></tr>\n";
            
            foreach ($tags as $tag) {
                $hierarchy = '';
                if ($tag['parent_id'] == 0) {
                    $hierarchy = '🏠 ROOT';
                } elseif ($tag['parent_id'] == 1) {
                    $hierarchy = '📁 Nivel 1';
                } else {
                    // Buscar el nombre del padre
                    $parentQuery = $db->getQuery(true)
                        ->select('title')
                        ->from('#__tags')
                        ->where('id = ' . (int)$tag['parent_id']);
                    $db->setQuery($parentQuery);
                    $parentTitle = $db->loadResult();
                    
                    $hierarchy = '└─ Hijo de: ' . ($parentTitle ?: 'ID:' . $tag['parent_id']);
                }
                
                echo "<tr>";
                echo "<td>{$tag['id']}</td>";
                echo "<td>{$tag['title']}</td>";
                echo "<td>{$tag['alias']}</td>";
                echo "<td>{$tag['parent_id']}</td>";
                echo "<td>{$tag['level']}</td>";
                echo "<td>{$tag['lft']}</td>";
                echo "<td>{$tag['rgt']}</td>";
                echo "<td>{$tag['path']}</td>";
                echo "<td>" . ($tag['published'] ? 'Sí' : 'No') . "</td>";
                echo "<td>{$hierarchy}</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
            
            // Mostrar estadísticas
            $totalTags = count($tags);
            $rootChildren = count(array_filter($tags, function($tag) { return $tag['parent_id'] == 1; }));
            $hierarchical = count(array_filter($tags, function($tag) { return $tag['parent_id'] > 1; }));
            
            echo "<h2>Estadísticas</h2>\n";
            echo "<p><strong>Total de tags:</strong> {$totalTags}</p>\n";
            echo "<p><strong>Tags de nivel 1 (hijos directos de ROOT):</strong> {$rootChildren}</p>\n";
            echo "<p><strong>Tags con jerarquía (nivel 2+):</strong> {$hierarchical}</p>\n";
            
            // Verificar problemas en nested set
            $problems = array_filter($tags, function($tag) { return $tag['lft'] >= $tag['rgt']; });
            if (!empty($problems)) {
                echo "<h2 style='color: red;'>⚠️ Problemas detectados en Nested Set</h2>\n";
                foreach ($problems as $problem) {
                    echo "<p style='color: red;'>Tag '{$problem['title']}' tiene lft({$problem['lft']}) >= rgt({$problem['rgt']})</p>\n";
                }
            } else {
                echo "<h2 style='color: green;'>✅ Nested Set íntegro</h2>\n";
                echo "<p style='color: green;'>No se detectaron problemas en la estructura nested set.</p>\n";
            }
            
        } else {
            echo "<p>No se encontraron tags.</p>\n";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>\n";
    }
    
    echo "<p><a href='index.php?option=com_tagimport'>Volver al componente</a></p>\n";
    echo "</body></html>";
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

/**
 * Get available tags for parent selection
 */
function getAvailableParentTags()
{
    $db = Factory::getDbo();
    $tags = [];
    
    try {
        $query = $db->getQuery(true)
            ->select('id, title, alias, parent_id, level, path')
            ->from('#__tags')
            ->where('id > 1') // Exclude ROOT tag
            ->where('published = 1') // Only published tags
            ->order('lft ASC'); // Order by nested set left value for proper hierarchy
        
        $db->setQuery($query);
        $results = $db->loadAssocList();
        
        if ($results) {
            // Add ROOT option
            $tags[] = [
                'id' => 1,
                'title' => '🏠 ROOT (Sin padre)',
                'alias' => 'root',
                'level' => 0,
                'indent' => ''
            ];
            
            // Process results to show hierarchy with indentation
            foreach ($results as $tag) {
                $indent = str_repeat('└─ ', max(0, $tag['level'] - 1));
                $hierarchyIndicator = '';
                
                if ($tag['level'] == 1) {
                    $hierarchyIndicator = '📁 ';
                } elseif ($tag['level'] > 1) {
                    $hierarchyIndicator = '📄 ';
                }
                
                $tags[] = [
                    'id' => $tag['id'],
                    'title' => $hierarchyIndicator . $tag['title'],
                    'alias' => $tag['alias'],
                    'level' => $tag['level'],
                    'indent' => $indent,
                    'path' => $tag['path']
                ];
            }
        } else {
            // No tags available, only ROOT
            $tags[] = [
                'id' => 1,
                'title' => '🏠 ROOT (Sin padre)',
                'alias' => 'root',
                'level' => 0,
                'indent' => ''
            ];
        }
        
        Log::add('Tags disponibles para parent selection: ' . count($tags), Log::DEBUG, 'com_tagimport');
        
    } catch (Exception $e) {
        Log::add('Error obteniendo tags para parent selection: ' . $e->getMessage(), Log::ERROR, 'com_tagimport');
        
        // Fallback to ROOT only
        $tags[] = [
            'id' => 1,
            'title' => '🏠 ROOT (Sin padre)',
            'alias' => 'root',
            'level' => 0,
            'indent' => ''
        ];
    }
    
    return $tags;
}

// Get current status
$tagStatus = getTagImportStatus();

// Handle form submission
$message = '';
$messageType = '';
$previewData = null;

// Handle special debug action
if (isset($_GET['debug']) && $_GET['debug'] === 'status') {
    showTagsStatus();
    exit;
}

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
                    // Get global parent ID from POST
                    $globalParentId = isset($_POST['global_parent_id']) ? (int)$_POST['global_parent_id'] : 1;
                    Log::add('Global parent ID seleccionado: ' . $globalParentId, Log::INFO, 'com_tagimport');
                    
                    $importResult = processTagImport($data, $globalParentId);
                    
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
                
            } else if ($action === 'rebuild') {
                // Handle nested set rebuild
                $rebuildResult = rebuildTagsNestedSet();
                
                if ($rebuildResult['success']) {
                    $message = 'Estructura jerárquica de tags reconstruida exitosamente';
                    $messageType = 'success';
                } else {
                    $message = 'Error reconstruyendo estructura: ' . $rebuildResult['error'];
                    $messageType = 'error';
                }
            } else if ($action === 'fix_hierarchy') {
                // Handle manual hierarchy fix for test tags
                $fixResult = fixTestHierarchy();
                
                if ($fixResult['success']) {
                    $message = $fixResult['message'];
                    $messageType = 'success';
                } else {
                    $message = 'Error corrigiendo jerarquía: ' . $fixResult['error'];
                    $messageType = 'error';
                }
            }
        }
    } else {
        Log::add('Token inválido o faltante. Token esperado: ' . $token . ', Tokens enviados: ' . json_encode(array_keys($_POST)), Log::ERROR, 'com_tagimport');
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
<html lang="<?php echo $app->getLanguage()->getTag(); ?>">
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
    
    <script>
        // Sync parent selection with hidden field
        document.addEventListener('DOMContentLoaded', function() {
            const parentSelect = document.getElementById('global_parent_id');
            const hiddenField = document.getElementById('hidden_global_parent_id');
            
            if (parentSelect && hiddenField) {
                parentSelect.addEventListener('change', function() {
                    hiddenField.value = this.value;
                });
                
                // Initialize hidden field with current selection
                hiddenField.value = parentSelect.value;
            }
        });
    </script>
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
            <form action="index.php?option=com_tagimport" method="post" enctype="multipart/form-data">
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
                        <th>Padre</th>
                        <th>Nivel</th>
                        <th>Ruta</th>
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
                        <td>
                            <?php 
                            if (isset($tag['parent_alias'])) {
                                echo '<span style="color: blue;">📂 ' . htmlspecialchars($tag['parent_alias']) . '</span>';
                            } elseif (isset($tag['parent_id']) && $tag['parent_id'] == 1) {
                                echo '<span style="color: green;">🏠 ROOT</span>';
                            } else {
                                echo '<span style="color: red;">❓ ID:' . ($tag['parent_id'] ?? '?') . '</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <span style="padding-left: <?php echo (($tag['level'] ?? 1) - 1) * 15; ?>px;">
                                <?php echo str_repeat('└─ ', max(0, ($tag['level'] ?? 1) - 1)); ?>
                                Nivel <?php echo $tag['level'] ?? 1; ?>
                            </span>
                        </td>
                        <td><code><?php echo htmlspecialchars($tag['path'] ?? $tag['alias'] ?? ''); ?></code></td>
                        <td><?php echo isset($tag['published']) && $tag['published'] ? Text::_('JYES') : Text::_('JNO'); ?></td>
                    </tr>
                    <?php endfor; ?>
                    <?php if (count($previewData) > 10): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; font-style: italic;">
                            <?php echo sprintf(Text::_('COM_TAGIMPORT_MORE_ITEMS'), count($previewData) - 10); ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Parent Tag Selection -->
            <div class="parent-selection-section" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; border-left: 4px solid #28a745;">
                <h3>🔗 Configuración de Jerarquía</h3>
                <p>Selecciona un tag padre para todos los tags de esta importación:</p>
                
                <?php $availableParents = getAvailableParentTags(); ?>
                <div class="form-group">
                    <label for="global_parent_id" style="font-weight: bold;">Tag Padre para esta importación:</label>
                    <select name="global_parent_id" id="global_parent_id" style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ced4da; border-radius: 4px;">
                        <?php foreach ($availableParents as $parentTag): ?>
                            <option value="<?php echo $parentTag['id']; ?>" <?php echo $parentTag['id'] == 1 ? 'selected' : ''; ?>>
                                <?php echo $parentTag['indent']; ?><?php echo htmlspecialchars($parentTag['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="display: block; margin-top: 5px; color: #666;">
                        💡 <strong>ROOT:</strong> Los tags se crearán en el nivel superior.<br>
                        📁 <strong>Tag existente:</strong> Los tags se crearán como hijos del tag seleccionado.
                    </small>
                </div>
                
                <div class="hierarchy-info" style="margin-top: 10px; padding: 10px; background: #e3f2fd; border-radius: 4px;">
                    <strong>ℹ️ Información:</strong>
                    <ul style="margin: 5px 0 0 20px;">
                        <li>Los tags con <code>parent_alias</code> en el JSON mantendrán su jerarquía interna</li>
                        <li>Los tags sin parent_alias se asignarán al tag padre seleccionado</li>
                        <li>Puedes cambiar la jerarquía individualmente después de importar</li>
                    </ul>
                </div>
            </div>
            
            <!-- Import and Clear buttons -->
            <form action="index.php?option=com_tagimport" method="post" style="display: inline;">
                <button type="submit" class="btn btn-success"><?php echo Text::_('COM_TAGIMPORT_IMPORT_NOW'); ?></button>
                <input type="hidden" name="action" value="import">
                <input type="hidden" name="global_parent_id" id="hidden_global_parent_id" value="1">
                <input type="hidden" name="<?php echo Factory::getApplication()->getFormToken(); ?>" value="1">
            </form>
            
            <form action="index.php?option=com_tagimport" method="post" style="display: inline;">
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
            
            <form action="index.php?option=com_tagimport" method="post" onsubmit="return confirm('<?php echo Text::_('COM_TAGIMPORT_RESET_CONFIRM'); ?>');">
                <button type="submit" class="btn btn-danger"><?php echo Text::_('COM_TAGIMPORT_RESET'); ?></button>
                <input type="hidden" name="action" value="reset">
                <input type="hidden" name="<?php echo Factory::getApplication()->getFormToken(); ?>" value="1">
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Maintenance Tools Section -->
        <div class="card">
            <h2>🔧 Herramientas de Mantenimiento</h2>
            <p>Utilidades para reparar y mantener la estructura de tags.</p>
            
            <form action="index.php?option=com_tagimport" method="post" style="display: inline; margin-right: 10px;">
                <button type="submit" class="btn btn-warning" onclick="return confirm('¿Reconstruir la estructura jerárquica de todos los tags?\\n\\nEsto corregirá problemas de nested set y jerarquía.');">
                    🔧 Reconstruir Jerarquía de Tags
                </button>
                <input type="hidden" name="action" value="rebuild">
                <input type="hidden" name="<?php echo Factory::getApplication()->getFormToken(); ?>" value="1">
            </form>
            
            <small class="text-muted">
                ⚠️ Usa esta herramienta si los tags no aparecen en el campo "Principal" o hay errores de edición.
            </small>
            
            <div style="margin-top: 15px;">
                <a href="index.php?option=com_tagimport&debug=status" class="btn btn-secondary" target="_blank">🔍 Ver Estado Actual de Tags</a>
                <a href="components/com_tagimport/fix_hierarchy.php" class="btn btn-warning" target="_blank">🔧 Corregir Jerarquía Manualmente</a>
                <small style="display: block; margin-top: 5px; color: #666;">
                    Ver estado: Muestra tabla detallada con todos los tags. | Corregir: Repara relaciones parent_id rotas.
                </small>
            </div>
        </div>
        
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
