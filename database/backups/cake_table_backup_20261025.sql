-- KúkiCakes cake table backup before pricing update
-- Generated: 2026-10-25 04:54:39
-- Total rows: 95

DROP TABLE IF EXISTS `cake_backup_20261025`;
CREATE TABLE `cake_backup_20261025` LIKE `cake`;
INSERT INTO `cake_backup_20261025` SELECT * FROM `cake`;

