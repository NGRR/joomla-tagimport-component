-- Script de diagnóstico para tabla de tags
-- Base de datos: dbJoomla5, Prefijo: adv_

USE `dbJoomla5`;

-- 1. Ver estructura actual de todos los tags
SELECT 
    id, 
    parent_id, 
    lft, 
    rgt, 
    level, 
    title, 
    alias, 
    path,
    published,
    checked_out,
    access
FROM `adv_tags` 
ORDER BY lft;

-- 2. Detectar problemas de nested set
-- Tags con lft/rgt = 0 (problema crítico)
SELECT 'PROBLEMA: lft/rgt = 0' as issue, id, title, lft, rgt FROM `adv_tags` WHERE lft = 0 OR rgt = 0;

-- 3. Detectar gaps en nested set
-- El rgt debe ser mayor que lft
SELECT 'PROBLEMA: rgt <= lft' as issue, id, title, lft, rgt FROM `adv_tags` WHERE rgt <= lft;

-- 4. Verificar consistencia parent/level
-- Level debe coincidir con la profundidad real
SELECT 
    'PROBLEMA: level inconsistente' as issue,
    t1.id, 
    t1.title, 
    t1.parent_id, 
    t1.level,
    t2.title as parent_title,
    t2.level as parent_level
FROM `adv_tags` t1 
LEFT JOIN `adv_tags` t2 ON t1.parent_id = t2.id 
WHERE t1.level != (COALESCE(t2.level, -1) + 1);

-- 5. Verificar paths
SELECT 'INFO: Paths actuales' as info, id, title, alias, path FROM `adv_tags` ORDER BY id;

-- 6. Tags con checked_out != 0 (pueden causar problemas de edición)
SELECT 'PROBLEMA: Tags bloqueados' as issue, id, title, checked_out, checked_out_time FROM `adv_tags` WHERE checked_out != 0;

-- ===== RESUMEN CONSOLIDADO =====
SELECT 
    'RESUMEN EJECUTIVO' as categoria,
    'Total de tags' as problema,
    COUNT(*) as cantidad,
    '' as detalle
FROM `adv_tags`

UNION ALL

SELECT 
    'CRÍTICO' as categoria,
    'Tags con lft/rgt = 0' as problema,
    COUNT(*) as cantidad,
    GROUP_CONCAT(CONCAT(id, ':', title) SEPARATOR ', ') as detalle
FROM `adv_tags` 
WHERE lft = 0 OR rgt = 0

UNION ALL

SELECT 
    'CRÍTICO' as categoria,
    'Tags con rgt <= lft' as problema,
    COUNT(*) as cantidad,
    GROUP_CONCAT(CONCAT(id, ':', title) SEPARATOR ', ') as detalle
FROM `adv_tags` 
WHERE rgt <= lft

UNION ALL

SELECT 
    'ADVERTENCIA' as categoria,
    'Tags bloqueados (checked_out)' as problema,
    COUNT(*) as cantidad,
    GROUP_CONCAT(CONCAT(id, ':', title, ' (user:', checked_out, ')') SEPARATOR ', ') as detalle
FROM `adv_tags` 
WHERE checked_out != 0

UNION ALL

SELECT 
    'INFO' as categoria,
    'Tags con jerarquía válida' as problema,
    COUNT(*) as cantidad,
    GROUP_CONCAT(CONCAT(id, ':', title) SEPARATOR ', ') as detalle
FROM `adv_tags` 
WHERE lft > 0 AND rgt > lft AND checked_out = 0

UNION ALL

SELECT 
    'ESTADO' as categoria,
    CASE 
        WHEN (SELECT COUNT(*) FROM `adv_tags` WHERE lft = 0 OR rgt = 0) > 0 THEN 'REQUIERE REPARACIÓN URGENTE'
        WHEN (SELECT COUNT(*) FROM `adv_tags` WHERE checked_out != 0) > 0 THEN 'REQUIERE DESBLOQUEOO'
        ELSE 'ESTRUCTURA OK'
    END as problema,
    0 as cantidad,
    'Ejecutar botón Reconstruir Jerarquía del componente' as detalle
FROM DUAL

ORDER BY 
    FIELD(categoria, 'RESUMEN EJECUTIVO', 'CRÍTICO', 'ADVERTENCIA', 'INFO', 'ESTADO'),
    cantidad DESC;
