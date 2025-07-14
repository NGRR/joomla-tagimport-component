-- Create the import log table
CREATE TABLE IF NOT EXISTS `#__categoryimport_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `category_id` int(11) NOT NULL,
    `original_id` int(11) DEFAULT NULL,
    `import_date` datetime NOT NULL,
    `import_user_id` int(11) NOT NULL,
    `title` varchar(255) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_category_id` (`category_id`),
    KEY `idx_import_date` (`import_date`),
    KEY `idx_import_user_id` (`import_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
