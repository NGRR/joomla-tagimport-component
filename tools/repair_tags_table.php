<?php
/**
 * Script para reparar la tabla de tags de Joomla
 * Reconstruye el nested set corrupto
 */

define('_JEXEC', 1);
define('JPATH_BASE', dirname(__FILE__) . '/../../..');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table as JTable;

try {
    $app = Factory::getApplication('administrator');
    $db = Factory::getDbo();
    
    echo "=== REPARACIÓN DE TABLA DE TAGS ===\n";
    
    // 1. Eliminar todos los tags excepto el root
    echo "1. Eliminando tags de prueba...\n";
    $query = $db->getQuery(true)
        ->delete($db->quoteName('#__tags'))
        ->where($db->quoteName('id') . ' > 1');
    $db->setQuery($query);
    $result = $db->execute();
    echo "   Tags eliminados: " . $db->getAffectedRows() . "\n";
    
    // 2. Verificar el tag root
    echo "2. Verificando tag root...\n";
    $query = $db->getQuery(true)
        ->select('*')
        ->from('#__tags')
        ->where('id = 1');
    $db->setQuery($query);
    $rootTag = $db->loadObject();
    
    if (!$rootTag) {
        echo "   ERROR: Tag root no existe, creándolo...\n";
        $query = $db->getQuery(true)
            ->insert('#__tags')
            ->columns(['id', 'parent_id', 'lft', 'rgt', 'level', 'path', 'title', 'alias', 'note', 'description', 'published', 'checked_out', 'checked_out_time', 'access', 'params', 'metadesc', 'metakey', 'metadata', 'created_user_id', 'created_time', 'created_by_alias', 'modified_user_id', 'modified_time', 'images', 'urls', 'hits', 'language', 'version'])
            ->values("1, 0, 1, 2, 0, '', 'ROOT', 'root', '', '', 1, 0, NULL, 1, '{}', '', '', '{}', 42, NOW(), '', 42, NOW(), '{}', '{}', 0, '*', 1");
        $db->setQuery($query);
        $db->execute();
    } else {
        echo "   Tag root existe con ID: " . $rootTag->id . "\n";
        
        // Reparar valores nested set del root si están corruptos
        if ($rootTag->lft != 1 || $rootTag->rgt != 2 || $rootTag->level != 0) {
            echo "   Reparando valores nested set del root...\n";
            $query = $db->getQuery(true)
                ->update('#__tags')
                ->set('lft = 1, rgt = 2, level = 0, parent_id = 0')
                ->where('id = 1');
            $db->setQuery($query);
            $db->execute();
        }
    }
    
    // 3. Resetear AUTO_INCREMENT
    echo "3. Reseteando AUTO_INCREMENT...\n";
    $db->setQuery("ALTER TABLE #__tags AUTO_INCREMENT = 2");
    $db->execute();
    
    // 4. Verificar estructura final
    echo "4. Verificando estructura final...\n";
    $query = $db->getQuery(true)
        ->select('id, parent_id, lft, rgt, level, title, alias')
        ->from('#__tags')
        ->order('lft');
    $db->setQuery($query);
    $tags = $db->loadObjectList();
    
    foreach ($tags as $tag) {
        echo "   ID: {$tag->id}, Parent: {$tag->parent_id}, Lft: {$tag->lft}, Rgt: {$tag->rgt}, Level: {$tag->level}, Title: {$tag->title}\n";
    }
    
    echo "\n=== REPARACIÓN COMPLETADA ===\n";
    echo "La tabla de tags ha sido reparada. Ahora puedes:\n";
    echo "1. Probar crear un tag desde la interfaz de Joomla\n";
    echo "2. Importar tags usando el componente\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
