<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$reference = '';
$email = '';
$order = null;
$items = [];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $referenceInput = $_POST['reference'] ?? '';
    $emailInput = $_POST['email'] ?? '';
    $reference = is_string($referenceInput) ? strtoupper(trim($referenceInput)) : '';
    $email = is_string($emailInput) ? trim($emailInput) : '';

    if (!preg_match('/\AORD-\d{8}-[A-F0-9]{4}\z/', $reference) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid order reference and email address.';
    } else {
        $statement = $pdo->prepare(
            'SELECT id, reference, customer_name, total, status, created_at
             FROM orders WHERE reference = :reference AND email = :email LIMIT 1'
        );
        $statement->execute(['reference' => $reference, 'email' => $email]);
        $order = $statement->fetch();
        if ($order) {
            $itemsStatement = $pdo->prepare(
                'SELECT product_name, unit_price, quantity FROM order_items
                 WHERE order_id = :order_id ORDER BY product_name'
            );
            $itemsStatement->execute(['order_id' => $order['id']]);
            $items = $itemsStatement->fetchAll();
        } else {
            $error = 'We could not find an order matching those details.';
        }
    }
}

$pageTitle = 'Order lookup';
require __DIR__ . '/partials/header.php';
?>
<section class="checkout-page order-lookup-page">
    <p class="eyebrow">Here for you</p>
    <h1>Track your order</h1>
    <p class="checkout-lead">Enter your order reference and the email used at checkout to check the latest status.</p>
    <form class="checkout-form lookup-form" method="post" action="<?= e(url('order-lookup.php')) ?>">
        <?= csrf_field() ?>
        <?php if ($error !== ''): ?><div class="admin-alert admin-alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <label for="reference">Order reference</label>
        <input id="reference" name="reference" type="text" maxlength="20" placeholder="ORD-20261102-7F3K" value="<?= e($reference) ?>" required>
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" maxlength="160" autocomplete="email" value="<?= e($email) ?>" required>
        <button class="button button-dark" type="submit">Find my order <span aria-hidden="true">→</span></button>
    </form>
    <?php if ($order): ?>
        <div class="lookup-result">
            <div class="admin-panel-heading">
                <h2><?= e($order['reference']) ?></h2>
                <span class="status-pill status-<?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span>
            </div>
            <p class="lookup-order-date">Placed <?= e(date('M j, Y', strtotime($order['created_at']))) ?></p>
            <?php foreach ($items as $item): ?>
                <p class="lookup-item"><span><?= e($item['product_name']) ?> × <?= (int) $item['quantity'] ?></span><strong><?= e(money((float) $item['unit_price'] * (int) $item['quantity'])) ?></strong></p>
            <?php endforeach; ?>
            <p class="lookup-item lookup-total"><span>Order total</span><strong><?= e(money($order['total'])) ?></strong></p>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
