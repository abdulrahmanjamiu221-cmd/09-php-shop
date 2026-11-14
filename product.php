<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$slug = isset($_GET['slug']) && is_string($_GET['slug']) ? trim($_GET['slug']) : '';
if ($slug === '') {
    http_response_code(404);
    exit('Product not found.');
}

$statement = $pdo->prepare(
    'SELECT p.id, p.name, p.slug, p.description, p.price, p.stock, p.image_url, c.name AS category_name
     FROM products p
     INNER JOIN categories c ON c.id = p.category_id
     WHERE p.slug = :slug AND p.is_active = 1
     LIMIT 1'
);
$statement->execute(['slug' => $slug]);
$product = $statement->fetch();
if (!$product) {
    http_response_code(404);
    exit('Product not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $quantityInput = $_POST['quantity'] ?? '';
    $quantity = filter_var($quantityInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($quantity === false) {
        set_flash('error', 'Choose a valid quantity.');
    } elseif ((int) $product['stock'] < 1) {
        set_flash('error', 'This piece is currently out of stock.');
    } else {
        set_flash('notice', cart_add((int) $product['id'], $quantity));
    }
    header('Location: ' . url('product.php?slug=' . rawurlencode($product['slug'])));
    exit;
}

$pageTitle = (string) $product['name'];
require __DIR__ . '/partials/header.php';
?>
<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="<?= e(url()) ?>">Shop</a><span aria-hidden="true">/</span>
    <span><?= e($product['category_name']) ?></span>
</nav>
<section class="product-detail">
    <div class="detail-image">
        <img src="<?= e($product['image_url']) ?>" alt="<?= e($product['name']) ?>">
        <?php if ((int) $product['stock'] < 1): ?>
            <span class="stock-badge">Out of stock</span>
        <?php endif; ?>
    </div>
    <div class="detail-copy">
        <p class="eyebrow"><?= e($product['category_name']) ?></p>
        <h1><?= e($product['name']) ?></h1>
        <p class="detail-price"><?= e(money($product['price'])) ?></p>
        <div class="detail-description"><?= nl2br(e($product['description']), false) ?></div>
        <?php if ((int) $product['stock'] > 0): ?>
            <p class="stock-note"><?= (int) $product['stock'] ?> in stock</p>
        <?php endif; ?>
        <form class="add-form" method="post" action="<?= e(url('product.php?slug=' . rawurlencode($product['slug']))) ?>">
            <?= csrf_field() ?>
            <label for="quantity">Quantity</label>
            <input id="quantity" name="quantity" type="number" min="1" max="<?= (int) $product['stock'] ?>" value="1" required <?= (int) $product['stock'] < 1 ? 'disabled' : '' ?>>
            <button class="button button-dark" type="submit" <?= (int) $product['stock'] < 1 ? 'disabled' : '' ?>>
                <?= (int) $product['stock'] < 1 ? 'Out of stock' : 'Add to bag' ?>
                <?php if ((int) $product['stock'] > 0): ?><span aria-hidden="true">→</span><?php endif; ?>
            </button>
        </form>
        <p class="detail-footnote">Carefully selected. Made for everyday living.</p>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
