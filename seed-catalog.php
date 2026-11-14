<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This seeder can only be run from the command line.\n");
}

require_once __DIR__ . '/app/bootstrap.php';

$catalog = [
    ['Home', 'home', 'Sunday Stoneware Vase', 'sunday-stoneware-vase', 'A softly sculpted stoneware vase with a tactile matte glaze.', 18500, 12],
    ['Home', 'home', 'Linen Table Runner', 'linen-table-runner', 'Washed natural linen for everyday meals and special gatherings.', 24000, 8],
    ['Home', 'home', 'Woven Market Basket', 'woven-market-basket', 'A sturdy handwoven basket for market mornings and weekend picnics.', 32000, 6],
    ['Kitchen', 'kitchen', 'Daily Ceramic Mug', 'daily-ceramic-mug', 'A comfortable mug with a generous handle and warm speckled finish.', 9500, 24],
    ['Kitchen', 'kitchen', 'Olive Wood Serving Spoon', 'olive-wood-serving-spoon', 'A smooth olive wood spoon with naturally varied grain.', 7000, 18],
    ['Kitchen', 'kitchen', 'Glass Water Carafe', 'glass-water-carafe', 'A clear, softly rounded carafe for everyday hydration.', 16500, 10],
    ['Accessories', 'accessories', 'Everywhere Canvas Tote', 'everywhere-canvas-tote', 'An everyday cotton canvas carryall for groceries and books.', 12500, 20],
    ['Accessories', 'accessories', 'Soft Cotton Bandana', 'soft-cotton-bandana', 'A versatile, breathable cotton bandana for everyday wear.', 6500, 15],
    ['Accessories', 'accessories', 'Weekend Leather Card Case', 'weekend-leather-card-case', 'A slim, thoughtfully stitched leather card case.', 22000, 4],
    ['Wellbeing', 'wellbeing', 'Calm Down Soy Candle', 'calm-down-soy-candle', 'A clean-burning soy candle with cedar and citrus notes.', 14000, 14],
    ['Wellbeing', 'wellbeing', 'Everyday Hand Balm', 'everyday-hand-balm', 'A nourishing, non-greasy hand balm for dry hands.', 8500, 0],
    ['Wellbeing', 'wellbeing', 'Slow Morning Tea Set', 'slow-morning-tea-set', 'A calming herbal blend with a reusable infuser.', 12000, 9],
    ['Stationery', 'stationery', 'The Good Days Notebook', 'the-good-days-notebook', 'A lay-flat notebook for lists, plans, and good thoughts.', 8000, 30],
    ['Stationery', 'stationery', 'Little Notes Greeting Cards', 'little-notes-greeting-cards', 'Six illustrated cards for birthdays, thank-yous, and notes.', 5500, 25],
    ['Stationery', 'stationery', 'Desk Companion Pen Set', 'desk-companion-pen-set', 'Two smooth-writing pens in a simple gift box.', 10500, 11],
];

try {
    $pdo->beginTransaction();
    $categoryStatement = $pdo->prepare(
        'INSERT INTO categories (name, slug) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name)'
    );
    $productStatement = $pdo->prepare(
        'INSERT INTO products (category_id, name, slug, description, price, stock, image_url, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), name = VALUES(name),
            description = VALUES(description), price = VALUES(price), stock = VALUES(stock),
            image_url = VALUES(image_url), is_active = 1'
    );

    $categoryIds = [];
    foreach ($catalog as $item) {
        [$categoryName, $categorySlug] = $item;
        if (!isset($categoryIds[$categorySlug])) {
            $categoryStatement->execute([$categoryName, $categorySlug]);
            $lookup = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
            $lookup->execute([$categorySlug]);
            $categoryIds[$categorySlug] = (int) $lookup->fetchColumn();
        }
        [$categoryName, $categorySlug, $name, $slug, $description, $price, $stock] = $item;
        $imageUrl = 'https://placehold.co/600x400?text=' . rawurlencode($name);
        $productStatement->execute([
            $categoryIds[$categorySlug], $name, $slug, $description, $price, $stock, $imageUrl,
        ]);
    }

    $pdo->commit();
    fwrite(STDOUT, 'Seeded ' . count($categoryIds) . ' categories and ' . count($catalog) . " products.\n");
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Seeding failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
