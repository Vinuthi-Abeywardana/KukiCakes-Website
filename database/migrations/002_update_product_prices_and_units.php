<?php
/**
 * KúkiCakes — Database Migration: 002_update_product_prices_and_units.php
 * Date: 2026-10-25
 *
 * Implements approved product prices and sale units from the authoritative
 * KúkiCakes Pricing Worksheet (KukiCakes_Pricing_Worksheet_Completed.xlsx).
 *
 * Actions:
 * 1. Checks target database is 'kukicakes_db'.
 * 2. Idempotently adds 'sale_unit' and 'confirmed_size' columns to `cake` table.
 * 3. Updates all 95 products with their approved Final Price (Rs.), sale unit, and confirmed size.
 * 4. Preserves all existing cake names, descriptions, images, badges, and categories.
 */

date_default_timezone_set('Asia/Colombo');

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Forbidden: Database migrations may only be executed via the command line.\n");
}

require_once __DIR__ . '/../../config/db.php';

$results = [];

// 1. Verify target database
$dbNameStmt = $pdo->query("SELECT DATABASE()");
$currentDb = $dbNameStmt->fetchColumn();

if ($currentDb !== 'kukicakes_db') {
    die("ABORT: Expected database 'kukicakes_db', but connected to '{$currentDb}'.\n");
}
$results[] = "✓ Verified target database: {$currentDb}";

// 2. Add sale_unit and confirmed_size columns idempotently
$columnsToAdd = [
    'sale_unit' => [
        'def' => "VARCHAR(50) NULL DEFAULT NULL",
        'after' => 'price'
    ],
    'confirmed_size' => [
        'def' => "VARCHAR(100) NULL DEFAULT NULL",
        'after' => 'sale_unit'
    ]
];

foreach ($columnsToAdd as $colName => $colInfo) {
    $check = $pdo->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'cake' 
          AND COLUMN_NAME = ?
    ");
    $check->execute([$colName]);
    $exists = ((int) $check->fetchColumn()) > 0;

    if (!$exists) {
        $afterCol = $colInfo['after'];
        $checkAfter = $pdo->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'cake' 
              AND COLUMN_NAME = ?
        ");
        $checkAfter->execute([$afterCol]);
        $afterExists = ((int) $checkAfter->fetchColumn()) > 0;

        $afterClause = $afterExists ? "AFTER `{$afterCol}`" : "";
        $sql = "ALTER TABLE `cake` ADD COLUMN `{$colName}` {$colInfo['def']} {$afterClause}";
        $pdo->exec($sql);
        $results[] = "✓ Added column `cake`.`{$colName}` ({$colInfo['def']}).";
    } else {
        $results[] = "→ Column `cake`.`{$colName}` already exists, skipped.";
    }
}

// 3. Approved pricing data from authoritative worksheet
$pricingData = [
    1 => ['name' => 'Golden Pearl Anniversary Cake', 'price' => 10300.00, 'unit' => 'Whole cake', 'size' => '1.5 kg, round, tall buttercream'],
    2 => ['name' => 'Burgundy Ombre Heart Anniversary Cake', 'price' => 10100.00, 'unit' => 'Whole cake', 'size' => '1.5 kg, round, ombre buttercream'],
    3 => ['name' => 'Magenta Hydrangea Anniversary Cake', 'price' => 11000.00, 'unit' => 'Whole cake', 'size' => '1.5 kg, round, piped buttercream'],
    4 => ['name' => 'Together Forever Gold Cascade Cake', 'price' => 11400.00, 'unit' => 'Whole cake', 'size' => '1.5 kg, round, gold-leaf cascade'],
    5 => ['name' => 'Golden Drip Anniversary Cake', 'price' => 7300.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, round, drip'],
    6 => ['name' => 'Modern MuseRed Anniversary Cake', 'price' => 7100.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, round, hand-painted'],
    7 => ['name' => 'SpongeBob Kids Birthday Cake', 'price' => 9000.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, fondant character cake'],
    8 => ['name' => 'Hello Kitty Kids Birthday Cake', 'price' => 8100.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, buttercream + fondant figure'],
    9 => ['name' => 'Jungle Safari Adventure KidsBirthday Cake', 'price' => 10400.00, 'unit' => 'Whole cake', 'size' => '1.2 kg, fondant animal figures'],
    10 => ['name' => 'Lucas&apos;s Dino Volcano Kids Adventure Birthday Cake', 'price' => 12900.00, 'unit' => 'Whole cake', 'size' => '1.5 kg, fondant volcano + dinosaurs'],
    11 => ['name' => 'Pastel Smiley Rainbow Kids Birthday Cake', 'price' => 6700.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, buttercream kawaii face'],
    12 => ['name' => 'Spider-Man Kids Birthday Cake', 'price' => 8800.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, fondant, 3D figure'],
    13 => ['name' => 'Safari Freinds Kids Birthday Cake', 'price' => 9300.00, 'unit' => 'Whole cake', 'size' => '1.2 kg, buttercream + fondant animals'],
    14 => ['name' => 'Golden 21st Birthday Cake', 'price' => 7800.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, round, sugar-sphere cascade'],
    15 => ['name' => 'Black and White 21st Birthday Cake', 'price' => 6600.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, round, piped lace'],
    16 => ['name' => 'Gold Drip 21st Birthday Cake', 'price' => 8700.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, matte fondant'],
    17 => ['name' => 'Blush Rosette Mirror 21st Cake', 'price' => 10800.00, 'unit' => 'Whole cake', 'size' => '1.5 kg, full buttercream rosettes'],
    18 => ['name' => 'Rustic Baby&apos;s Breath 21st Cake', 'price' => 5800.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, textured buttercream'],
    19 => ['name' => 'Disco Nights 21st Birthday Cake', 'price' => 11200.00, 'unit' => 'Whole cake', 'size' => '1.5 kg, buttercream with props'],
    20 => ['name' => 'Golden Truffle 21st Coming-Up Cake', 'price' => 12300.00, 'unit' => 'Whole cake', 'size' => '1.5 kg, ganache drip'],
    59 => ['name' => 'Classic Black Forest Cake', 'price' => 6500.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, round'],
    60 => ['name' => 'Cherry Chocolate Shavings Forest Cake', 'price' => 6900.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, round'],
    61 => ['name' => 'Triple-Layer Chocolate Rosette Cake', 'price' => 9500.00, 'unit' => 'Whole cake', 'size' => '1.5 kg, 3-layer'],
    62 => ['name' => 'Glossy Ganache Chocolate Cake', 'price' => 7500.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, round'],
    63 => ['name' => 'Chocolate Truffle Decadence Cake', 'price' => 8000.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, round'],
    64 => ['name' => 'Rustic Naked Chocolate Cake', 'price' => 6300.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, naked style'],
    65 => ['name' => 'Golden Peach Rose 90th Birthday Cake', 'price' => 8200.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, round'],
    66 => ['name' => 'Golden Balloon 50th Birthday Cake', 'price' => 8900.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, round'],
    67 => ['name' => 'Starlight Gold 80th Birthday Cake', 'price' => 7400.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, round'],
    68 => ['name' => 'Fresh Strawberry Rosette Cake', 'price' => 8000.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, round'],
    69 => ['name' => 'Strawberry Sprinkle Delight Cake', 'price' => 11600.00, 'unit' => 'Whole cake', 'size' => '1.5 kg, round'],
    70 => ['name' => 'Traditional Glazed Christmas Fruit Cake', 'price' => 9300.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, dark fruit cake'],
    71 => ['name' => 'Festive Wreath Christmas Cake', 'price' => 7300.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, round'],
    72 => ['name' => 'Buche de Noel Yule Log', 'price' => 9000.00, 'unit' => 'Whole log', 'size' => '1 log, about 1.0 kg'],
    73 => ['name' => 'Snowflake &amp; Holly Christmas Cake', 'price' => 7000.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, round'],
    74 => ['name' => 'Golden Crust Traditional Fruit Cake', 'price' => 6600.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, baked fruit cake'],
    75 => ['name' => 'Blush Rose Swirl Cupcakes', 'price' => 3300.00, 'unit' => 'Box of 6', 'size' => 'Box of 6 cupcakes (assumed)'],
    76 => ['name' => 'Navy &amp; Gold Wave Cupcakes', 'price' => 4100.00, 'unit' => 'Box of 6', 'size' => 'Box of 6 cupcakes (assumed)'],
    77 => ['name' => 'Dusty Blue Rosette Cupcakes', 'price' => 3600.00, 'unit' => 'Box of 6', 'size' => 'Box of 6 cupcakes (assumed)'],
    78 => ['name' => 'Pink Mauve Red Velvet Cupcakes', 'price' => 3800.00, 'unit' => 'Box of 6', 'size' => 'Box of 6 cupcakes (assumed)'],
    79 => ['name' => 'Sparkling Pink Rose Cupcakes', 'price' => 3700.00, 'unit' => 'Box of 6', 'size' => 'Box of 6 cupcakes (assumed)'],
    80 => ['name' => 'Mocha &amp; Ivory Rose Cupcakes', 'price' => 3900.00, 'unit' => 'Box of 6', 'size' => 'Box of 6 cupcakes (assumed)'],
    81 => ['name' => 'Ivory Pearl Wedding Ring Cupcakes', 'price' => 3400.00, 'unit' => 'Box of 6', 'size' => 'Box of 6 cupcakes (assumed)'],
    82 => ['name' => 'Burgundy Rose Half-Dozen Cupcakes', 'price' => 4700.00, 'unit' => 'Box of 6', 'size' => 'Box of 6 cupcakes'],
    83 => ['name' => 'Ivory Rose Wedding Cupcake Tower', 'price' => 11200.00, 'unit' => 'Box of 24', 'size' => 'Tower of 24 cupcakes (assumed)'],
    84 => ['name' => 'Gilded Ivory Rose Wedding Cupcakes', 'price' => 6500.00, 'unit' => 'Box of 12', 'size' => 'Box of 12 cupcakes (assumed)'],
    85 => ['name' => 'Golden Rings Engagement Cake', 'price' => 22500.00, 'unit' => 'Whole cake', 'size' => '2-tier, about 2.5 kg'],
    86 => ['name' => 'Draped Fringe Engagement Cake', 'price' => 22500.00, 'unit' => 'Whole cake', 'size' => '2-tier, about 2.5 kg'],
    87 => ['name' => 'S&amp;H Champagne Bubble Engagement Cake', 'price' => 23500.00, 'unit' => 'Whole cake', 'size' => '2-tier, about 2.5 kg'],
    88 => ['name' => 'Engaged in Ivory Rose Cake', 'price' => 17000.00, 'unit' => 'Whole cake', 'size' => '1 tier, about 2.0 kg'],
    89 => ['name' => 'Just Engaged Blush Watercolor Cake', 'price' => 15400.00, 'unit' => 'Whole cake', 'size' => '1 tier, about 2.0 kg'],
    90 => ['name' => 'Gold Crack Pearl Engagement Cake', 'price' => 17400.00, 'unit' => 'Whole cake', 'size' => '1 tier, about 2.0 kg'],
    91 => ['name' => 'Pearl Rose Ring Engagement Cake', 'price' => 18100.00, 'unit' => 'Whole cake', 'size' => '1 tier, about 2.0 kg'],
    92 => ['name' => 'Onyx &amp; Gold Graduate&apos;s Triumph Cake', 'price' => 11800.00, 'unit' => 'Whole cake', 'size' => '1.5 kg, round, fondant'],
    93 => ['name' => 'Gilded Class of 2025 Celebration Cake', 'price' => 10600.00, 'unit' => 'Whole cake', 'size' => '1.5 kg, round'],
    94 => ['name' => 'Zahraa&apos;s Gilded Achievement Cake', 'price' => 9400.00, 'unit' => 'Whole cake', 'size' => '1.2 kg, round'],
    95 => ['name' => 'Pearl Elegance Graduation Cake', 'price' => 7400.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, round'],
    96 => ['name' => 'Golden Rosette Congratulations Cake', 'price' => 12500.00, 'unit' => 'Whole cake', 'size' => '2-tier, about 2.0 kg'],
    97 => ['name' => 'She Did It! Rustic Rose Graduate Cake', 'price' => 10600.00, 'unit' => 'Whole cake', 'size' => '1.5 kg, rustic'],
    98 => ['name' => 'Sunshine Rosette Number 5 Cake', 'price' => 7100.00, 'unit' => 'Number cake', 'size' => 'Single digit, about 0.9 kg'],
    99 => ['name' => 'Emerald Macaron Number 8 Cake', 'price' => 9000.00, 'unit' => 'Number cake', 'size' => 'Single digit, about 1.0 kg'],
    100 => ['name' => 'Berry Bliss Number 20 Cake', 'price' => 11700.00, 'unit' => 'Number cake', 'size' => 'Double digit, about 1.4 kg'],
    101 => ['name' => 'Lea&apos;s Strawberry Meringue Number 19 Cake', 'price' => 11500.00, 'unit' => 'Number cake', 'size' => 'Double digit, about 1.4 kg'],
    102 => ['name' => 'Floral Meringue Number 20 Cake', 'price' => 11800.00, 'unit' => 'Number cake', 'size' => 'Double digit, about 1.4 kg'],
    103 => ['name' => 'Pastel Dream Number 1 Cake', 'price' => 7400.00, 'unit' => 'Number cake', 'size' => 'Single digit, about 0.9 kg'],
    104 => ['name' => 'Golden Butterfly Number 40 Cake', 'price' => 11200.00, 'unit' => 'Number cake', 'size' => 'Double digit, about 1.4 kg'],
    105 => ['name' => 'Rainbow Delight Bite Squares', 'price' => 7700.00, 'unit' => 'Box of 24', 'size' => '24 bite squares'],
    106 => ['name' => 'Classic Ruby Red Velvet Cake', 'price' => 5300.00, 'unit' => 'Whole cake', 'size' => 'About 0.75 kg, round'],
    107 => ['name' => 'Red Velvet Rosette &amp; Chocolate Bark Cake', 'price' => 10700.00, 'unit' => 'Whole cake', 'size' => '1.2 kg, round'],
    108 => ['name' => 'Pandan Ribbon Layer Cake', 'price' => 8300.00, 'unit' => 'Whole cake', 'size' => '1.5 kg, ribbon layer'],
    109 => ['name' => 'Crimson Rose Ombre Wedding Cake', 'price' => 41500.00, 'unit' => 'Whole cake', 'size' => '3-tier, about 4.5 kg (assumed fully edible)'],
    110 => ['name' => 'Grand Garden Rose Wedding Cake', 'price' => 87500.00, 'unit' => 'Whole cake', 'size' => '4-tier, about 9 kg (assumed fully edible)'],
    111 => ['name' => 'J&amp;N Pearl &amp; Rose Wedding Cake', 'price' => 66500.00, 'unit' => 'Whole cake', 'size' => '4-tier, about 8 kg (assumed fully edible)'],
    112 => ['name' => 'N&amp;S Rose Gold Champagne Wedding Cake', 'price' => 38500.00, 'unit' => 'Whole cake', 'size' => '3-tier, about 4.5 kg (assumed fully edible)'],
    113 => ['name' => 'Draped Curtain Baby&apos;s Breath Wedding Cake', 'price' => 32500.00, 'unit' => 'Whole cake', 'size' => '3-tier, about 4.5 kg (assumed fully edible)'],
    114 => ['name' => 'Bridal Lace Applique Wedding Cake', 'price' => 59000.00, 'unit' => 'Whole cake', 'size' => '4-tier, about 7 kg (assumed fully edible)'],
    115 => ['name' => 'S&amp;M Burgundy Rose Wedding Cake', 'price' => 34000.00, 'unit' => 'Whole cake', 'size' => '3-tier, about 4.5 kg (assumed fully edible)'],
    116 => ['name' => 'Cascading Rose Grand Wedding Cake', 'price' => 83500.00, 'unit' => 'Whole cake', 'size' => '5-tier, about 9 kg (assumed fully edible)'],
    117 => ['name' => 'Draped Sash Pearl Wedding Cake', 'price' => 81000.00, 'unit' => 'Whole cake', 'size' => '5-tier, about 10 kg (assumed fully edible)'],
    118 => ['name' => 'Y&amp;M Pearl Cascade Wedding Cake', 'price' => 77500.00, 'unit' => 'Whole cake', 'size' => '5-tier, about 9 kg (assumed fully edible)'],
    119 => ['name' => 'Cinnamon Apple Spiral Cake', 'price' => 4900.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, spiral'],
    120 => ['name' => 'Rustic Banana Walnut Loaf', 'price' => 3000.00, 'unit' => 'Loaf', 'size' => '1 loaf, about 0.7 kg'],
    121 => ['name' => 'Golden Crosshatch Butter Cake', 'price' => 3100.00, 'unit' => 'Whole cake', 'size' => 'About 0.8 kg'],
    122 => ['name' => 'Classic Coffee Rosette Cake', 'price' => 5800.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, round'],
    123 => ['name' => 'Classic Date &amp; Walnut Delight Cake', 'price' => 5900.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, round'],
    124 => ['name' => 'Caramel Date &amp; Walnut Cake', 'price' => 6000.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, round'],
    125 => ['name' => 'Date &amp; Walnut Caramel Loaf', 'price' => 3500.00, 'unit' => 'Loaf', 'size' => '1 loaf, about 0.7 kg'],
    126 => ['name' => 'Tropical Medley Fresh Fruit Cake', 'price' => 10300.00, 'unit' => 'Whole cake', 'size' => '1.5 kg, round'],
    127 => ['name' => 'Tropical Fruit &amp; Almond Gateau', 'price' => 8800.00, 'unit' => 'Whole cake', 'size' => '1.2 kg, round'],
    128 => ['name' => 'Molten Chocolate Lava Cake', 'price' => 5900.00, 'unit' => 'Box of 6', 'size' => '6 individual lava cakes (assumed)'],
    129 => ['name' => 'Zesty Lemon Bundt Cake', 'price' => 4300.00, 'unit' => 'Whole cake', 'size' => 'About 0.9 kg bundt'],
    130 => ['name' => 'Lemon Zest Rosette Layer Cake', 'price' => 5500.00, 'unit' => 'Whole cake', 'size' => '1.0 kg, layer cake'],
    131 => ['name' => 'Classic Golden Sponge Cake', 'price' => 2800.00, 'unit' => 'Whole cake', 'size' => 'About 0.8 kg'],
    132 => ['name' => 'Chocolate Ganache Swiss Roll', 'price' => 3800.00, 'unit' => 'Roll', 'size' => '1 roll, about 0.6 kg'],
    133 => ['name' => 'Strawberry Cream Swiss Roll', 'price' => 3700.00, 'unit' => 'Roll', 'size' => '1 roll, about 0.6 kg'],
];

// 4. Update cake records
$updateStmt = $pdo->prepare("
    UPDATE `cake` 
    SET `price` = :price, 
        `sale_unit` = :unit, 
        `confirmed_size` = :size 
    WHERE `cake_id` = :id
");

$updatedCount = 0;
$pdo->beginTransaction();
try {
    foreach ($pricingData as $id => $info) {
        $updateStmt->execute([
            ':price' => $info['price'],
            ':unit'  => $info['unit'],
            ':size'  => $info['size'],
            ':id'    => $id
        ]);
        $updatedCount++;
    }
    $pdo->commit();
    $results[] = "✓ Successfully updated {$updatedCount} products with approved final prices and sale units.";
} catch (Exception $e) {
    $pdo->rollBack();
    die("ERROR during pricing update: " . $e->getMessage() . "\n");
}

// 5. Output summary
echo "KúkiCakes — Database Migration: 002_update_product_prices_and_units\n";
echo str_repeat('=', 65) . "\n";
foreach ($results as $res) {
    echo $res . "\n";
}

echo "\nSample Updated Cake Records (IDs 1-5, 75, 83, 98, 100, 109, 120, 128):\n";
echo str_repeat('-', 65) . "\n";
$sampleStmt = $pdo->query("SELECT cake_id, cake_name, price, sale_unit, confirmed_size FROM `cake` WHERE cake_id IN (1, 2, 3, 4, 5, 75, 83, 98, 100, 109, 120, 128) ORDER BY cake_id ASC");
while ($row = $sampleStmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf(
        "ID %-3d | %-32s | Rs. %-8s | %-12s | %s\n",
        $row['cake_id'],
        substr($row['cake_name'], 0, 32),
        number_format($row['price'], 2),
        $row['sale_unit'],
        $row['confirmed_size']
    );
}
