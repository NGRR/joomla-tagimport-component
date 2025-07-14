# Tag Import Component
Componente Joomla para importación masiva de tags desde archivos JSON

## Descripción
El componente Tag Import permite la importación masiva de etiquetas (tags) en Joomla 5 desde archivos JSON, con soporte para jerarquías y seguimiento de importaciones.

## Características

### ✅ Funcionalidades Implementadas
- **Importación desde JSON**: Carga archivos JSON con estructura de tags
- **Previsualización**: Vista previa de los tags antes de importar
- **Tracking de importaciones**: Seguimiento de qué tags fueron importados
- **Reset de importaciones**: Eliminación de tags importados previamente
- **Interfaz de administración**: Panel completo en el backend de Joomla
- **Logging detallado**: Registro completo de todas las operaciones
- **Validación de datos**: Verificación de estructura JSON y datos requeridos
- **Manejo de errores**: Gestión robusta de errores y excepciones

### 🔧 Herramientas de Mantenimiento
- **Reconstrucción de nested set**: Reparación de la estructura jerárquica
- **Diagnóstico visual**: Vista detallada del estado de todos los tags
- **Verificación de integridad**: Detección de problemas en la estructura

## Versión Actual
**v1.1.0** - Estado funcional básico estable

### Estado del Proyecto
- **✅ Funcional**: Importación básica de tags (con parent ROOT)
- **🔄 En desarrollo**: Sistema de selección de parent tag para jerarquías
- **Estable**: No hay corrupción del sistema, tags editables normalmente

## Instalación
1. Comprimir la carpeta `admin/` y los archivos del componente
2. Instalar como extensión en Joomla
3. Acceder desde Componentes > Tag Import

## Estructura de JSON Esperada
```json
[
  {
    "title": "Título del Tag",
    "alias": "alias-del-tag",
    "description": "Descripción opcional",
    "published": 1,
    "parent_alias": "alias-del-padre-opcional"
  }
]
```

## Archivos Principales
- `admin/tagimport.php` - Archivo principal del componente
- `admin/tagimport.xml` - Manifest del componente
- `admin/config.xml` - Configuración del componente
- `admin/language/` - Archivos de idioma

## Log de Cambios
### v1.1.0 (Estado Actual)
- Refactorización completa para tags (desde categorías)
- Implementación de sistema de tracking
- Herramientas de mantenimiento y diagnóstico
- Funcionalidad de reset seguro
- Logging exhaustivo con JLog
- Estado funcional básico estable

## Próximas Funcionalidades
- Sistema de selección de tag padre para jerarquías
- Importación jerárquica automática mejorada
- Validaciones adicionales de integridad
