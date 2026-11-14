<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$reference = isset($_GET['ref']) && is_string($_GET['ref']) ? strtoupper(trim($_GET['ref'])) : '';
$sessionReference = $_SESSION['last_order_reference'] ?? '';
if (!preg_match('/\AORD-\d{8}-[A-F0-9]{4}\z/', $reference) ||
    !is_string($sessionReference) ||
    !hash_equals($sessionReference, $reference)
) {
    http_response_code(404);
    exit('Order not found.');
}

$orderStatement = $pdo->prepare(
    'SELECT id, reference, customer_name, total, status FROM orders WHERE reference = :reference LIMIT 1'
);
$orderStatement->execute(['reference' => $reference]);
$order = $orderStatement->fetch();
if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

$itemsStatement = $pdo->prepare(
    'SELECT product_name, unit_price, quantity FROM order_items WHERE order_id = :order_id ORDER BY product_name'
);
$itemsStatement->execute(['order_id' => $order['id']]);
$orderItems = $itemsStatement->fetchAll();

$pageTitle = 'Order confirmed';
require __DIR__ . '/partials/header.php';
?>
<section class="checkout-page order-confirmation">
    <p class="eyebrow">Thank you</p>
    <h1>Your order is in.</h1>
    <p class="checkout-lead">Thanks, <?= e($order['customer_name']) ?>. We’ve saved your order as pending.</p>
    <div class="confirmation-card">
        <p><span>Order reference</span><strong><?= e($order['reference']) ?></strong></p>
        <p><span>Status</span><strong><?= e(ucfirst($order['status'])) ?></strong></p>
        <?php foreach ($orderItems as $item): ?>
            <p><span><?= e($item['product_name']) ?> × <?= (int) $item['quantity'] ?></span><strong><?= e(money((float) $item['unit_price'] * (int) $item['quantity'])) ?></strong></p>
        <?php endforeach; ?>
        <p class="confirmation-total"><span>Total</span><strong><?= e(money($order['total'])) ?></strong></p>
    </div>
    <p class="summary-note">No payment was collected. Your order is pending confirmation.</p>
    <a class="button button-dark" href="<?= e(url()) ?>">Back to the shop <span aria-hidden="true">→</span></a>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
