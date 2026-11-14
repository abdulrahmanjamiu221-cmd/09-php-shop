<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    $productId = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($action === 'clear') {
        cart_clear();
        set_flash('success', 'Your bag has been cleared.');
    } elseif ($productId === false || !in_array($action, ['update', 'remove'], true)) {
        set_flash('error', 'That bag update was not valid.');
    } elseif ($action === 'remove') {
        cart_remove($productId);
        set_flash('success', 'Item removed from your bag.');
    } else {
        $quantity = filter_var($_POST['quantity'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($quantity === false) {
            set_flash('error', 'Choose a quantity of at least one.');
        } else {
            set_flash('notice', cart_update($productId, $quantity));
        }
    }

    header('Location: ' . url('cart.php'));
    exit;
}

$items = cart_items();
$subtotal = cart_total();
$pageTitle = 'Your bag';
require __DIR__ . '/partials/header.php';
?>
<section class="cart-page">
    <p class="eyebrow">A few good things</p>
    <h1>Your bag <span class="cart-title-count">(<?= cart_count() ?>)</span></h1>
    <?php if (!$items): ?>
        <div class="empty-state cart-empty">
            <span class="empty-icon">✳</span>
            <h3>Your bag is taking a breather</h3>
            <p>When you find something you love, it will be waiting here.</p>
            <a class="button button-dark" href="<?= e(url()) ?>">Explore the collection <span aria-hidden="true">→</span></a>
        </div>
    <?php else: ?>
        <div class="cart-table-wrap">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th scope="col">Item</th>
                        <th scope="col">Price</th>
                        <th scope="col">Quantity</th>
                        <th scope="col">Total</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php $updateFormId = 'update-' . (int) $item['id']; ?>
                        <tr>
                            <td>
                                <div class="cart-product">
                                    <a class="cart-item-image" href="<?= e(url('product.php?slug=' . rawurlencode($item['slug']))) ?>">
                                        <img src="<?= e($item['image_url']) ?>" alt="">
                                    </a>
                                    <div>
                                        <a class="cart-product-name" href="<?= e(url('product.php?slug=' . rawurlencode($item['slug']))) ?>"><?= e($item['name']) ?></a>
                                        <span class="cart-stock"><?= (int) $item['stock'] ?> in stock</span>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Price"><?= e(money($item['price'])) ?></td>
                            <td data-label="Quantity">
                                <form id="<?= e($updateFormId) ?>" method="post" action="<?= e(url('cart.php')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                                </form>
                                <div class="cart-quantity">
                                    <label class="sr-only" for="qty-<?= (int) $item['id'] ?>">Quantity for <?= e($item['name']) ?></label>
                                    <input form="<?= e($updateFormId) ?>" id="qty-<?= (int) $item['id'] ?>" type="number" name="quantity" min="1" max="<?= (int) $item['stock'] ?>" value="<?= (int) $item['quantity'] ?>" required>
                                    <button form="<?= e($updateFormId) ?>" class="text-link" type="submit">Update</button>
                                </div>
                            </td>
                            <td data-label="Total" class="cart-line-total"><?= e(money($item['line_total'])) ?></td>
                            <td class="cart-action-cell">
                                <form method="post" action="<?= e(url('cart.php')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                                    <button class="remove-link" type="submit">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="cart-bottom">
            <form method="post" action="<?= e(url('cart.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="clear">
                <button class="remove-link" type="submit">Clear bag</button>
            </form>
            <aside class="cart-summary">
                <h2>Summary</h2>
                <p><span>Subtotal</span><strong><?= e(money($subtotal)) ?></strong></p>
                <span class="summary-note">Shipping and taxes are not included in the subtotal.</span>
                <a class="button button-dark" href="<?= e(url('checkout.php')) ?>">Proceed to checkout <span aria-hidden="true">→</span></a>
            </aside>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
