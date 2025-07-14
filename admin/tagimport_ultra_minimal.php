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

// Iniciar búfer de salida para capturar cualquier output no deseado
ob_start();

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        error_log('[TAGIMPORT] FATAL ERROR: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']);
        Log::add('FATAL ERROR capturado: ' . $error['message'] . ' en línea ' . $error['line'], Log::ERROR, 'com_tagimport');
    }
});

/**
 * Process tag import from JSON data - SIMPLIFIED VERSION
 *
 * @param array $data Tag data from JSON
 * @return array Result with success, imported count, skipped count, and error message
 */
function processTagImport($data)
{
    // Log start with critical priority to ensure it's written
    Log::add('INICIO SIMPLIFICADO: processTagImport ejecutándose', Log::ERROR, 'com_tagimport');
    error_log('[TAGIMPORT] INICIO SIMPLIFICADO function at ' . date('Y-m-d H:i:s'));
    
    if (!is_array($data) || empty($data)) {
        Log::add('ERROR: Datos inválidos o vacíos', Log::ERROR, 'com_tagimport');
        return [
            'success' => false,
            'imported' => 0,
            'skipped' => 0,
            'error' => 'Invalid or empty data received'
        ];
    }
    
    try {
        // Simply log the data structure without processing
        Log::add('Datos recibidos: ' . count($data) . ' elementos', Log::ERROR, 'com_tagimport');
        
        if (count($data) > 0) {
            Log::add('Primer elemento: ' . json_encode(reset($data)), Log::ERROR, 'com_tagimport');
        }
        
        // Create tracking table - simplified version
        $db = Factory::getDbo();
        $prefix = $db->getPrefix();
        $tableName = $prefix . 'tagimport_tracking';
        
        // Check if table exists
        $tables = $db->getTableList();
        if (!in_array($tableName, $tables)) {
            Log::add('Creando tabla de tracking simplificada', Log::ERROR, 'com_tagimport');
            
            $sql = "CREATE TABLE IF NOT EXISTS `" . $tableName . "` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `tag_id` int(11) NOT NULL,
                `original_alias` varchar(191) NOT NULL,
                `imported_date` datetime NOT NULL,
                PRIMARY KEY (`id`)
            );";
            
            $db->setQuery($sql);
            $db->execute();
        }
        
        // Process the first tag from the array
        Log::add('Preparando importación del primer tag del JSON', Log::ERROR, 'com_tagimport');
        
        // Tomar el primer elemento del array para la prueba
        $tagData = reset($data);
        
        // Crear objeto de tag con valores por defecto
        $tag = new stdClass();
        
        // Campos obligatorios con valores por defecto
        $tag->parent_id = isset($tagData['parent_id']) ? $tagData['parent_id'] : 1;
        $tag->lft = 0;
        $tag->rgt = 0;
        $tag->level = isset($tagData['level']) ? $tagData['level'] : 1;
        $tag->path = isset($tagData['path']) ? $tagData['path'] : 'test-import-tag-' . time();
        $tag->title = isset($tagData['title']) ? $tagData['title'] : 'Test Import Tag';
        $tag->alias = isset($tagData['alias']) ? $tagData['alias'] : 'test-import-tag-' . time();
        $tag->note = isset($tagData['note']) ? $tagData['note'] : '';
        $tag->description = isset($tagData['description']) ? $tagData['description'] : 'Test tag created by simplified import function';
        $tag->published = isset($tagData['published']) ? $tagData['published'] : 1;
        $tag->checked_out = 0;
        $tag->checked_out_time = $db->getNullDate();
        $tag->access = isset($tagData['access']) ? $tagData['access'] : 1;
        
        // IMPORTANTE: Estos campos son los que causan el error si no se definen correctamente
        $tag->params = '{}'; // Valor por defecto aunque venga en el JSON
        $tag->metadesc = isset($tagData['metadesc']) ? $tagData['metadesc'] : '';
        $tag->metakey = isset($tagData['metakey']) ? $tagData['metakey'] : '';
        $tag->metadata = isset($tagData['metadata']) ? $tagData['metadata'] : '{}';
        $tag->created_user_id = isset($tagData['created_user_id']) ? $tagData['created_user_id'] : (Factory::getUser()->id ?: 42);
        $tag->created_time = isset($tagData['created_time']) ? $tagData['created_time'] : Factory::getDate()->toSql();
        $tag->created_by_alias = isset($tagData['created_by_alias']) ? $tagData['created_by_alias'] : '';
        $tag->modified_user_id = isset($tagData['modified_user_id']) ? $tagData['modified_user_id'] : (Factory::getUser()->id ?: 42);
        $tag->modified_time = isset($tagData['modified_time']) ? $tagData['modified_time'] : Factory::getDate()->toSql();
        $tag->images = isset($tagData['images']) ? $tagData['images'] : '{}';
        $tag->urls = isset($tagData['urls']) ? $tagData['urls'] : '{}';
        $tag->hits = isset($tagData['hits']) ? $tagData['hits'] : 0;
        $tag->language = isset($tagData['language']) ? $tagData['language'] : '*';
        $tag->version = isset($tagData['version']) ? $tagData['version'] : 1;
        
        // Obtener estructura de la tabla para ver qué campos son necesarios
        try {
            $db->setQuery('DESCRIBE ' . $db->quoteName('#__tags'));
            $tableFields = $db->loadObjectList('Field');
            Log::add('Estructura completa de tabla tags:', Log::ERROR, 'com_tagimport');
            
            foreach ($tableFields as $field) {
                Log::add('Campo: ' . $field->Field . ' | Tipo: ' . $field->Type . ' | Nulo: ' . $field->Null . ' | Default: ' . $field->Default, Log::ERROR, 'com_tagimport');
            }
            
            // Verificar específicamente el campo params
            $paramsField = null;
            foreach ($tableFields as $field) {
                if ($field->Field === 'params') {
                    $paramsField = $field;
                    break;
                }
            }
            
            if ($paramsField) {
                Log::add('Campo params encontrado - Nulo permitido: ' . $paramsField->Null . ' | Default: ' . ($paramsField->Default ?: 'NULL'), Log::ERROR, 'com_tagimport');
            } else {
                Log::add('ADVERTENCIA: Campo params no encontrado en la tabla', Log::ERROR, 'com_tagimport');
            }
            
        } catch (Exception $e) {
            Log::add('Error al obtener estructura de tabla: ' . $e->getMessage(), Log::ERROR, 'com_tagimport');
        }            // Garantizar que el campo params sea un objeto JSON válido
        if (!isset($tag->params) || empty($tag->params) || $tag->params === 'null') {
            $tag->params = '{}';
            Log::add('Forzando campo params a valor por defecto: {}', Log::ERROR, 'com_tagimport');
        }
        
        // Asegurar que los campos JSON sean strings, no objetos
        if (is_object($tag->params) || is_array($tag->params)) {
            $tag->params = json_encode($tag->params);
            Log::add('Convirtiendo params de objeto a string JSON', Log::ERROR, 'com_tagimport');
        }
        
        if (is_object($tag->metadata) || is_array($tag->metadata)) {
            $tag->metadata = json_encode($tag->metadata);
        }
        
        if (is_object($tag->images) || is_array($tag->images)) {
            $tag->images = json_encode($tag->images);
        }
        
        if (is_object($tag->urls) || is_array($tag->urls)) {
            $tag->urls = json_encode($tag->urls);
        }
        
        // Insert tag usando SQL directo para evitar problemas con insertObject
        Log::add('Intentando insertar tag con SQL directo', Log::ERROR, 'com_tagimport');
        
        // Preparar la consulta SQL INSERT explícita
        $query = $db->getQuery(true);
        $query->insert($db->quoteName('#__tags'))
            ->columns([
                $db->quoteName('parent_id'),
                $db->quoteName('lft'),
                $db->quoteName('rgt'), 
                $db->quoteName('level'),
                $db->quoteName('path'),
                $db->quoteName('title'),
                $db->quoteName('alias'),
                $db->quoteName('note'),
                $db->quoteName('description'),
                $db->quoteName('published'),
                $db->quoteName('checked_out'),
                $db->quoteName('checked_out_time'),
                $db->quoteName('access'),
                $db->quoteName('params'),
                $db->quoteName('metadesc'),
                $db->quoteName('metakey'),
                $db->quoteName('metadata'),
                $db->quoteName('created_user_id'),
                $db->quoteName('created_time'),
                $db->quoteName('created_by_alias'),
                $db->quoteName('modified_user_id'),
                $db->quoteName('modified_time'),
                $db->quoteName('images'),
                $db->quoteName('urls'),
                $db->quoteName('hits'),
                $db->quoteName('language'),
                $db->quoteName('version')
            ])
            ->values(
                $tag->parent_id . ', ' .
                $tag->lft . ', ' .
                $tag->rgt . ', ' .
                $tag->level . ', ' .
                $db->quote($tag->path) . ', ' .
                $db->quote($tag->title) . ', ' .
                $db->quote($tag->alias) . ', ' .
                $db->quote($tag->note) . ', ' .
                $db->quote($tag->description) . ', ' .
                $tag->published . ', ' .
                $tag->checked_out . ', ' .
                ($tag->checked_out_time ? $db->quote($tag->checked_out_time) : 'NULL') . ', ' .
                $tag->access . ', ' .
                $db->quote($tag->params) . ', ' .
                $db->quote($tag->metadesc) . ', ' .
                $db->quote($tag->metakey) . ', ' .
                $db->quote($tag->metadata) . ', ' .
                $tag->created_user_id . ', ' .
                $db->quote($tag->created_time) . ', ' .
                $db->quote($tag->created_by_alias) . ', ' .
                $tag->modified_user_id . ', ' .
                $db->quote($tag->modified_time) . ', ' .
                $db->quote($tag->images) . ', ' .
                $db->quote($tag->urls) . ', ' .
                $tag->hits . ', ' .
                $db->quote($tag->language) . ', ' .
                $tag->version
            );
        
        Log::add('Query SQL preparada: ' . (string) $query, Log::ERROR, 'com_tagimport');
        
        try {
            $db->setQuery($query);
            $db->execute();
            $tagId = $db->insertid();
            if ($tagId > 0) {
                Log::add('Tag creado exitosamente con ID: ' . $tagId, Log::ERROR, 'com_tagimport');
                
                // Add to tracking
                $tracking = new stdClass();
                $tracking->tag_id = $tagId;
                $tracking->original_alias = $tag->alias;
                $tracking->imported_date = Factory::getDate()->toSql();
                
                $db->insertObject('#__tagimport_tracking', $tracking);
                
                Log::add('FIN SIMPLIFICADO: processTagImport completado exitosamente', Log::ERROR, 'com_tagimport');
                error_log('[TAGIMPORT] FIN SIMPLIFICADO function completed successfully');
                
                return [
                    'success' => true,
                    'imported' => 1,
                    'skipped' => 0,
                    'errors' => []
                ];
            } else {
                Log::add('Error: No se pudo obtener el ID del tag insertado', Log::ERROR, 'com_tagimport');
                return [
                    'success' => false,
                    'imported' => 0,
                    'skipped' => 0,
                    'error' => 'No se pudo obtener el ID del tag insertado'
                ];
            }
        } catch (Exception $insertException) {
            $errorMsg = $insertException->getMessage();
            Log::add('Error al insertar tag con SQL directo: ' . $errorMsg, Log::ERROR, 'com_tagimport');
            
            // Captura específica para el error de field 'params' doesn't have a default value
            if (stripos($errorMsg, 'Field \'params\' doesn\'t have a default value') !== false) {
                Log::add('DIAGNÓSTICO: El campo params sigue causando problemas incluso con SQL directo', Log::ERROR, 'com_tagimport');
                Log::add('Valor de params enviado: [' . $tag->params . ']', Log::ERROR, 'com_tagimport');
            }
            
            return [
                'success' => false,
                'imported' => 0,
                'skipped' => 0,
                'error' => 'Error en inserción SQL: ' . $errorMsg
            ];
        }
    } catch (Exception $e) {
        Log::add('ERROR SIMPLIFICADO: ' . $e->getMessage(), Log::ERROR, 'com_tagimport');
        error_log('[TAGIMPORT] ERROR SIMPLIFICADO: ' . $e->getMessage());
        return [
            'success' => false,
            'imported' => 0,
            'skipped' => 0,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get tag import status from database
 *
 * @return array Status information
 */
function getTagImportStatus()
{
    Log::add('Obteniendo estado de importación de tags (versión mínima)', Log::DEBUG, 'com_tagimport');
    
    $db = Factory::getDbo();
    $status = [
        'total_tags' => 0,
        'recent_imports' => 0,
        'tracking_table_exists' => false,
        'last_import_date' => null,
        'controller_executed' => false
    ];
    
    try {
        // Get total tags count
        Log::add('Consultando total de tags en la base de datos', Log::DEBUG, 'com_tagimport');
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__tags')
            ->where('id > 1'); // Exclude root tag
        $db->setQuery($query);
        $status['total_tags'] = (int) $db->loadResult();
        
        Log::add('Total de tags encontrados: ' . $status['total_tags'], Log::INFO, 'com_tagimport');
        
    } catch (Exception $e) {
        Log::add('Error al obtener estado: ' . $e->getMessage(), Log::ERROR, 'com_tagimport');
        $status['error'] = $e->getMessage();
    }
    
    Log::add('Estado de importación obtenido: ' . json_encode($status), Log::DEBUG, 'com_tagimport');
    return $status;
}

// Get current status
$tagStatus = getTagImportStatus();

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Log::add('Procesando solicitud POST para carga de archivo (versión mínima)', Log::INFO, 'com_tagimport');
    
    $token = Factory::getApplication()->getFormToken();
    if (isset($_POST[$token]) && $_POST[$token] === '1') {
        Log::add('Token de seguridad validado correctamente', Log::DEBUG, 'com_tagimport');
        
        // Process file upload
        if (isset($_FILES['jsonfile']) && $_FILES['jsonfile']['error'] === 0) {
            $uploadedFile = $_FILES['jsonfile'];
            $fileName = $uploadedFile['name'];
            $tmpName = $uploadedFile['tmp_name'];
            
            Log::add('Archivo recibido: ' . $fileName . ' (tamaño: ' . filesize($tmpName) . ' bytes)', Log::INFO, 'com_tagimport');
            
            // Validate file type
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            Log::add('Validando extensión de archivo: ' . $fileExtension, Log::DEBUG, 'com_tagimport');
            
            if ($fileExtension === 'json') {
                Log::add('Archivo JSON válido, procediendo a leer contenido', Log::INFO, 'com_tagimport');
                
                // Read and validate JSON
                $jsonContent = file_get_contents($tmpName);
                Log::add('Contenido JSON leído: ' . strlen($jsonContent) . ' caracteres', Log::DEBUG, 'com_tagimport');
                
                $data = json_decode($jsonContent, true);
                
                if ($data !== null) {
                    $itemCount = is_array($data) ? count($data) : 0;
                    Log::add('JSON decodificado exitosamente: ' . $itemCount . ' elementos encontrados', Log::INFO, 'com_tagimport');
                    
                    // Log sample data for debugging
                    if ($itemCount > 0) {
                        $firstItem = is_array($data) ? reset($data) : $data;
                        Log::add('Primer elemento: ' . json_encode($firstItem), Log::DEBUG, 'com_tagimport');
                    }
                    
                    $message = 'JSON file uploaded successfully. Contains ' . $itemCount . ' items.';
                    $messageType = 'success';
                    
                    Log::add('Preparando procesamiento de importación de tags (versión mínima)', Log::INFO, 'com_tagimport');
                    
                    try {
                        Log::add('Llamando función processTagImport con ' . $itemCount . ' elementos', Log::ERROR, 'com_tagimport');
                        
                        // Asegurar que los datos tengan todos los campos necesarios
                        Log::add('Preparando datos para importación', Log::ERROR, 'com_tagimport');
                        
                        // Si es un array plano, asegurar que tenga todas las propiedades necesarias
                        if (is_array($data)) {
                            foreach ($data as &$item) {
                                if (!isset($item['params'])) $item['params'] = '{}';
                                if (!isset($item['metadata'])) $item['metadata'] = '{}';
                                if (!isset($item['metadesc'])) $item['metadesc'] = '';
                                if (!isset($item['metakey'])) $item['metakey'] = '';
                                if (!isset($item['images'])) $item['images'] = '{}';
                                if (!isset($item['urls'])) $item['urls'] = '{}';
                                if (!isset($item['note'])) $item['note'] = '';
                                if (!isset($item['language'])) $item['language'] = '*';
                                if (!isset($item['version'])) $item['version'] = 1;
                            }
                        }
                        
                        Log::add('Datos preparados para importación', Log::ERROR, 'com_tagimport');
                        
                        // Process tag import - WRAPPED IN ADDITIONAL TRY-CATCH
                        $importResult = processTagImport($data);
                        
                        Log::add('Función processTagImport completada', Log::ERROR, 'com_tagimport');
                        
                        if ($importResult['success']) {
                            $message = 'Tags imported successfully! ' . $importResult['imported'] . ' tags imported, ' . $importResult['skipped'] . ' skipped.';
                            $messageType = 'success';
                        } else {
                            $message = 'Error importing tags: ' . ($importResult['error'] ?? 'Unknown error');
                            $messageType = 'error';
                        }
                    } catch (Exception $e) {
                        Log::add('Error capturado al llamar processTagImport: ' . $e->getMessage(), Log::ERROR, 'com_tagimport');
                        $message = 'Error processing import: ' . $e->getMessage();
                        $messageType = 'error';
                    } catch (Throwable $t) {
                        Log::add('Error fatal capturado al llamar processTagImport: ' . $t->getMessage(), Log::ERROR, 'com_tagimport');
                        $message = 'Fatal error processing import: ' . $t->getMessage();
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Invalid JSON format. Could not decode file.';
                    $messageType = 'error';
                    Log::add('Error al decodificar JSON: ' . json_last_error_msg(), Log::ERROR, 'com_tagimport');
                }
            } else {
                $message = 'Invalid file format. Please upload a JSON file.';
                $messageType = 'error';
                Log::add('Extensión de archivo inválida: ' . $fileExtension, Log::ERROR, 'com_tagimport');
            }
        } else {
            $message = 'No file uploaded or upload error.';
            $messageType = 'warning';
            Log::add('Error en la carga de archivo: ' . ($_FILES['jsonfile']['error'] ?? 'No file'), Log::WARNING, 'com_tagimport');
        }
    } else {
        $message = 'Invalid security token.';
        $messageType = 'error';
        Log::add('Token de seguridad inválido', Log::ERROR, 'com_tagimport');
    }
}

// Capture any output from above
$debugOutput = ob_get_clean();
if (!empty($debugOutput)) {
    Log::add('Debug output capturado: ' . $debugOutput, Log::DEBUG, 'com_tagimport');
}

// Display interface
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tag Import - Ultra Minimal</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .alert-error, .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .card { background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-radius: 5px; margin: 20px 0; }
        .btn { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="file"] { padding: 8px; border: 1px solid #ced4da; border-radius: 4px; width: 100%; max-width: 400px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Tag Import - Ultra Minimal</h1>
        <p>This is a simplified interface for importing tags.</p>
        
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Import Status</h2>
            <p>Total tags in system: <?php echo $tagStatus['total_tags']; ?></p>
        </div>
        
        <div class="card">
            <h2>Import Tags</h2>
            <form action="" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="jsonfile">Select JSON File</label>
                    <input type="file" name="jsonfile" id="jsonfile" accept=".json" required>
                </div>
                <button type="submit" class="btn">Import Tags</button>
                <input type="hidden" name="<?php echo Factory::getApplication()->getFormToken(); ?>" value="1">
            </form>
        </div>
    </div>
</body>
</html>
