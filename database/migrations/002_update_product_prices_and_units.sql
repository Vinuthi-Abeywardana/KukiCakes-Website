-- =====================================================================
-- KúkiCakes — Database Migration: 002_update_product_prices_and_units.sql
-- Updates product prices and adds sale unit columns.
-- =====================================================================

USE `kukicakes_db`;

-- 1. Add columns if not exist
ALTER TABLE `cake`
    ADD COLUMN IF NOT EXISTS `sale_unit` VARCHAR(50) NULL DEFAULT NULL AFTER `price`,
    ADD COLUMN IF NOT EXISTS `confirmed_size` VARCHAR(100) NULL DEFAULT NULL AFTER `sale_unit`;

-- 2. Update prices and sale units
UPDATE `cake` SET `price` = 10300.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.5 kg, round, tall buttercream' WHERE `cake_id` = 1;
UPDATE `cake` SET `price` = 10100.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.5 kg, round, ombre buttercream' WHERE `cake_id` = 2;
UPDATE `cake` SET `price` = 11000.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.5 kg, round, piped buttercream' WHERE `cake_id` = 3;
UPDATE `cake` SET `price` = 11400.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.5 kg, round, gold-leaf cascade' WHERE `cake_id` = 4;
UPDATE `cake` SET `price` = 7300.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, round, drip' WHERE `cake_id` = 5;
UPDATE `cake` SET `price` = 7100.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, round, hand-painted' WHERE `cake_id` = 6;
UPDATE `cake` SET `price` = 9000.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, fondant character cake' WHERE `cake_id` = 7;
UPDATE `cake` SET `price` = 8100.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, buttercream + fondant figure' WHERE `cake_id` = 8;
UPDATE `cake` SET `price` = 10400.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.2 kg, fondant animal figures' WHERE `cake_id` = 9;
UPDATE `cake` SET `price` = 12900.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.5 kg, fondant volcano + dinosaurs' WHERE `cake_id` = 10;
UPDATE `cake` SET `price` = 6700.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, buttercream kawaii face' WHERE `cake_id` = 11;
UPDATE `cake` SET `price` = 8800.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, fondant, 3D figure' WHERE `cake_id` = 12;
UPDATE `cake` SET `price` = 9300.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.2 kg, buttercream + fondant animals' WHERE `cake_id` = 13;
UPDATE `cake` SET `price` = 7800.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, round, sugar-sphere cascade' WHERE `cake_id` = 14;
UPDATE `cake` SET `price` = 6600.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, round, piped lace' WHERE `cake_id` = 15;
UPDATE `cake` SET `price` = 8700.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, matte fondant' WHERE `cake_id` = 16;
UPDATE `cake` SET `price` = 10800.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.5 kg, full buttercream rosettes' WHERE `cake_id` = 17;
UPDATE `cake` SET `price` = 5800.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, textured buttercream' WHERE `cake_id` = 18;
UPDATE `cake` SET `price` = 11200.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.5 kg, buttercream with props' WHERE `cake_id` = 19;
UPDATE `cake` SET `price` = 12300.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.5 kg, ganache drip' WHERE `cake_id` = 20;
UPDATE `cake` SET `price` = 6500.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, round' WHERE `cake_id` = 59;
UPDATE `cake` SET `price` = 6900.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, round' WHERE `cake_id` = 60;
UPDATE `cake` SET `price` = 9500.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.5 kg, 3-layer' WHERE `cake_id` = 61;
UPDATE `cake` SET `price` = 7500.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, round' WHERE `cake_id` = 62;
UPDATE `cake` SET `price` = 8000.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, round' WHERE `cake_id` = 63;
UPDATE `cake` SET `price` = 6300.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, naked style' WHERE `cake_id` = 64;
UPDATE `cake` SET `price` = 8200.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, round' WHERE `cake_id` = 65;
UPDATE `cake` SET `price` = 8900.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, round' WHERE `cake_id` = 66;
UPDATE `cake` SET `price` = 7400.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, round' WHERE `cake_id` = 67;
UPDATE `cake` SET `price` = 8000.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, round' WHERE `cake_id` = 68;
UPDATE `cake` SET `price` = 11600.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.5 kg, round' WHERE `cake_id` = 69;
UPDATE `cake` SET `price` = 9300.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, dark fruit cake' WHERE `cake_id` = 70;
UPDATE `cake` SET `price` = 7300.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, round' WHERE `cake_id` = 71;
UPDATE `cake` SET `price` = 9000.00, `sale_unit` = 'Whole log', `confirmed_size` = '1 log, about 1.0 kg' WHERE `cake_id` = 72;
UPDATE `cake` SET `price` = 7000.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, round' WHERE `cake_id` = 73;
UPDATE `cake` SET `price` = 6600.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, baked fruit cake' WHERE `cake_id` = 74;
UPDATE `cake` SET `price` = 3300.00, `sale_unit` = 'Box of 6', `confirmed_size` = 'Box of 6 cupcakes (assumed)' WHERE `cake_id` = 75;
UPDATE `cake` SET `price` = 4100.00, `sale_unit` = 'Box of 6', `confirmed_size` = 'Box of 6 cupcakes (assumed)' WHERE `cake_id` = 76;
UPDATE `cake` SET `price` = 3600.00, `sale_unit` = 'Box of 6', `confirmed_size` = 'Box of 6 cupcakes (assumed)' WHERE `cake_id` = 77;
UPDATE `cake` SET `price` = 3800.00, `sale_unit` = 'Box of 6', `confirmed_size` = 'Box of 6 cupcakes (assumed)' WHERE `cake_id` = 78;
UPDATE `cake` SET `price` = 3700.00, `sale_unit` = 'Box of 6', `confirmed_size` = 'Box of 6 cupcakes (assumed)' WHERE `cake_id` = 79;
UPDATE `cake` SET `price` = 3900.00, `sale_unit` = 'Box of 6', `confirmed_size` = 'Box of 6 cupcakes (assumed)' WHERE `cake_id` = 80;
UPDATE `cake` SET `price` = 3400.00, `sale_unit` = 'Box of 6', `confirmed_size` = 'Box of 6 cupcakes (assumed)' WHERE `cake_id` = 81;
UPDATE `cake` SET `price` = 4700.00, `sale_unit` = 'Box of 6', `confirmed_size` = 'Box of 6 cupcakes' WHERE `cake_id` = 82;
UPDATE `cake` SET `price` = 11200.00, `sale_unit` = 'Box of 24', `confirmed_size` = 'Tower of 24 cupcakes (assumed)' WHERE `cake_id` = 83;
UPDATE `cake` SET `price` = 6500.00, `sale_unit` = 'Box of 12', `confirmed_size` = 'Box of 12 cupcakes (assumed)' WHERE `cake_id` = 84;
UPDATE `cake` SET `price` = 22500.00, `sale_unit` = 'Whole cake', `confirmed_size` = '2-tier, about 2.5 kg' WHERE `cake_id` = 85;
UPDATE `cake` SET `price` = 22500.00, `sale_unit` = 'Whole cake', `confirmed_size` = '2-tier, about 2.5 kg' WHERE `cake_id` = 86;
UPDATE `cake` SET `price` = 23500.00, `sale_unit` = 'Whole cake', `confirmed_size` = '2-tier, about 2.5 kg' WHERE `cake_id` = 87;
UPDATE `cake` SET `price` = 17000.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1 tier, about 2.0 kg' WHERE `cake_id` = 88;
UPDATE `cake` SET `price` = 15400.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1 tier, about 2.0 kg' WHERE `cake_id` = 89;
UPDATE `cake` SET `price` = 17400.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1 tier, about 2.0 kg' WHERE `cake_id` = 90;
UPDATE `cake` SET `price` = 18100.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1 tier, about 2.0 kg' WHERE `cake_id` = 91;
UPDATE `cake` SET `price` = 11800.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.5 kg, round, fondant' WHERE `cake_id` = 92;
UPDATE `cake` SET `price` = 10600.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.5 kg, round' WHERE `cake_id` = 93;
UPDATE `cake` SET `price` = 9400.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.2 kg, round' WHERE `cake_id` = 94;
UPDATE `cake` SET `price` = 7400.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, round' WHERE `cake_id` = 95;
UPDATE `cake` SET `price` = 12500.00, `sale_unit` = 'Whole cake', `confirmed_size` = '2-tier, about 2.0 kg' WHERE `cake_id` = 96;
UPDATE `cake` SET `price` = 10600.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.5 kg, rustic' WHERE `cake_id` = 97;
UPDATE `cake` SET `price` = 7100.00, `sale_unit` = 'Number cake', `confirmed_size` = 'Single digit, about 0.9 kg' WHERE `cake_id` = 98;
UPDATE `cake` SET `price` = 9000.00, `sale_unit` = 'Number cake', `confirmed_size` = 'Single digit, about 1.0 kg' WHERE `cake_id` = 99;
UPDATE `cake` SET `price` = 11700.00, `sale_unit` = 'Number cake', `confirmed_size` = 'Double digit, about 1.4 kg' WHERE `cake_id` = 100;
UPDATE `cake` SET `price` = 11500.00, `sale_unit` = 'Number cake', `confirmed_size` = 'Double digit, about 1.4 kg' WHERE `cake_id` = 101;
UPDATE `cake` SET `price` = 11800.00, `sale_unit` = 'Number cake', `confirmed_size` = 'Double digit, about 1.4 kg' WHERE `cake_id` = 102;
UPDATE `cake` SET `price` = 7400.00, `sale_unit` = 'Number cake', `confirmed_size` = 'Single digit, about 0.9 kg' WHERE `cake_id` = 103;
UPDATE `cake` SET `price` = 11200.00, `sale_unit` = 'Number cake', `confirmed_size` = 'Double digit, about 1.4 kg' WHERE `cake_id` = 104;
UPDATE `cake` SET `price` = 7700.00, `sale_unit` = 'Box of 24', `confirmed_size` = '24 bite squares' WHERE `cake_id` = 105;
UPDATE `cake` SET `price` = 5300.00, `sale_unit` = 'Whole cake', `confirmed_size` = 'About 0.75 kg, round' WHERE `cake_id` = 106;
UPDATE `cake` SET `price` = 10700.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.2 kg, round' WHERE `cake_id` = 107;
UPDATE `cake` SET `price` = 8300.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.5 kg, ribbon layer' WHERE `cake_id` = 108;
UPDATE `cake` SET `price` = 41500.00, `sale_unit` = 'Whole cake', `confirmed_size` = '3-tier, about 4.5 kg (assumed fully edible)' WHERE `cake_id` = 109;
UPDATE `cake` SET `price` = 87500.00, `sale_unit` = 'Whole cake', `confirmed_size` = '4-tier, about 9 kg (assumed fully edible)' WHERE `cake_id` = 110;
UPDATE `cake` SET `price` = 66500.00, `sale_unit` = 'Whole cake', `confirmed_size` = '4-tier, about 8 kg (assumed fully edible)' WHERE `cake_id` = 111;
UPDATE `cake` SET `price` = 38500.00, `sale_unit` = 'Whole cake', `confirmed_size` = '3-tier, about 4.5 kg (assumed fully edible)' WHERE `cake_id` = 112;
UPDATE `cake` SET `price` = 32500.00, `sale_unit` = 'Whole cake', `confirmed_size` = '3-tier, about 4.5 kg (assumed fully edible)' WHERE `cake_id` = 113;
UPDATE `cake` SET `price` = 59000.00, `sale_unit` = 'Whole cake', `confirmed_size` = '4-tier, about 7 kg (assumed fully edible)' WHERE `cake_id` = 114;
UPDATE `cake` SET `price` = 34000.00, `sale_unit` = 'Whole cake', `confirmed_size` = '3-tier, about 4.5 kg (assumed fully edible)' WHERE `cake_id` = 115;
UPDATE `cake` SET `price` = 83500.00, `sale_unit` = 'Whole cake', `confirmed_size` = '5-tier, about 9 kg (assumed fully edible)' WHERE `cake_id` = 116;
UPDATE `cake` SET `price` = 81000.00, `sale_unit` = 'Whole cake', `confirmed_size` = '5-tier, about 10 kg (assumed fully edible)' WHERE `cake_id` = 117;
UPDATE `cake` SET `price` = 77500.00, `sale_unit` = 'Whole cake', `confirmed_size` = '5-tier, about 9 kg (assumed fully edible)' WHERE `cake_id` = 118;
UPDATE `cake` SET `price` = 4900.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, spiral' WHERE `cake_id` = 119;
UPDATE `cake` SET `price` = 3000.00, `sale_unit` = 'Loaf', `confirmed_size` = '1 loaf, about 0.7 kg' WHERE `cake_id` = 120;
UPDATE `cake` SET `price` = 3100.00, `sale_unit` = 'Whole cake', `confirmed_size` = 'About 0.8 kg' WHERE `cake_id` = 121;
UPDATE `cake` SET `price` = 5800.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, round' WHERE `cake_id` = 122;
UPDATE `cake` SET `price` = 5900.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, round' WHERE `cake_id` = 123;
UPDATE `cake` SET `price` = 6000.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, round' WHERE `cake_id` = 124;
UPDATE `cake` SET `price` = 3500.00, `sale_unit` = 'Loaf', `confirmed_size` = '1 loaf, about 0.7 kg' WHERE `cake_id` = 125;
UPDATE `cake` SET `price` = 10300.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.5 kg, round' WHERE `cake_id` = 126;
UPDATE `cake` SET `price` = 8800.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.2 kg, round' WHERE `cake_id` = 127;
UPDATE `cake` SET `price` = 5900.00, `sale_unit` = 'Box of 6', `confirmed_size` = '6 individual lava cakes (assumed)' WHERE `cake_id` = 128;
UPDATE `cake` SET `price` = 4300.00, `sale_unit` = 'Whole cake', `confirmed_size` = 'About 0.9 kg bundt' WHERE `cake_id` = 129;
UPDATE `cake` SET `price` = 5500.00, `sale_unit` = 'Whole cake', `confirmed_size` = '1.0 kg, layer cake' WHERE `cake_id` = 130;
UPDATE `cake` SET `price` = 2800.00, `sale_unit` = 'Whole cake', `confirmed_size` = 'About 0.8 kg' WHERE `cake_id` = 131;
UPDATE `cake` SET `price` = 3800.00, `sale_unit` = 'Roll', `confirmed_size` = '1 roll, about 0.6 kg' WHERE `cake_id` = 132;
UPDATE `cake` SET `price` = 3700.00, `sale_unit` = 'Roll', `confirmed_size` = '1 roll, about 0.6 kg' WHERE `cake_id` = 133;
