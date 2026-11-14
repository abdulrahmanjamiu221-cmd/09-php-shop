<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$orderId = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($orderId === false) {
    http_response_code(404);
    exit('Order not found.');
}

$allowedTransitions = [
    'pending' => ['paid'],
    'paid' => ['shipped'],
    'shipped' => ['delivered'],
    'delivered' => [],
    'cancelled' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $newStatus = $_POST['status'] ?? '';
    if (!is_string($newStatus) || (!in_array($newStatus, ['cancelled'], true) && !in_array($newStatus, ['paid', 'shipped', 'delivered'], true))) {
        set_flash('error', 'Choose a valid order status.');
        header('Location: ' . url('admin/order.php?id=' . $orderId));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $orderLock = $pdo->prepare('SELECT id, status FROM orders WHERE id = :id FOR UPDATE');
        $orderLock->execute(['id' => $orderId]);
        $lockedOrder = $orderLock->fetch();
        if (!$lockedOrder) {
            $pdo->rollBack();
            http_response_code(404);
            exit('Order not found.');
        }

        $currentStatus = (string) $lockedOrder['status'];
        $isAllowed = in_array($newStatus, $allowedTransitions[$currentStatus] ?? [], true);
        $canCancel = $newStatus === 'cancelled' && !in_array($currentStatus, ['cancelled', 'delivered'], true);
        if (!$isAllowed && !$canCancel) {
            $pdo->rollBack();
            set_flash('error', 'That status change is not allowed.');
            header('Location: ' . url('admin/order.php?id=' . $orderId));
            exit;
        }

        if ($newStatus === 'cancelled') {
            $lineStatement = $pdo->prepare(
                'SELECT product_id, quantity FROM order_items WHERE order_id = :order_id ORDER BY product_id FOR UPDATE'
            );
            $lineStatement->execute(['order_id' => $orderId]);
            $restoreStock = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
            foreach ($lineStatement->fetchAll() as $line) {
                $restoreStock->execute([(int) $line['quantity'], (int) $line['product_id']]);
                if ($restoreStock->rowCount() !== 1) {
                    throw new RuntimeException('An ordered product could not be restocked.');
                }
            }
        }

        $updateOrder = $pdo->prepare('UPDATE orders SET status = :status WHERE id = :id');
        $updateOrder->execute(['status' => $newStatus, 'id' => $orderId]);
        $pdo->commit();
        set_flash('success', $newStatus === 'cancelled'
            ? 'Order cancelled and item stock restored.'
            : 'Order status updated to ' . $newStatus . '.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Admin order status update failed: ' . $exception->getMessage());
        set_flash('error', 'The order status could not be changed. No changes were saved.');
    }

    header('Location: ' . url('admin/order.php?id=' . $orderId));
    exit;
}

$orderStatement = $pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
$orderStatement->execute(['id' => $orderId]);
$order = $orderStatement->fetch();
if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

$itemsStatement = $pdo->prepare(
    'SELECT product_id, product_name, unit_price, quantity
     FROM order_items WHERE order_id = :order_id ORDER BY product_name'
);
$itemsStatement->execute(['order_id' => $orderId]);
$items = $itemsStatement->fetchAll();
$nextStatuses = $allowedTransitions[$order['status']] ?? [];
$canCancel = !in_array($order['status'], ['cancelled', 'delivered'], true);

$pageTitle = 'Order ' . $order['reference'];
require __DIR__ . '/partials/header.php';
?>
<div class="admin-breadcrumb"><a href="<?= e(url('admin/orders.php')) ?>">← All orders</a></div>
<section class="admin-page-heading">
    <div><p class="eyebrow">Order details</p><h1><?= e($order['reference']) ?></h1></div>
    <span class="status-pill status-<?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span>
</section>
<div class="order-detail-grid">
    <section class="admin-panel">
        <div class="admin-panel-heading"><h2>Items</h2><span><?= count($items) ?> lines</span></div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Product</th><th>Unit price</th><th>Quantity</th><th>Line total</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= e($item['product_name']) ?></td>
                        <td><?= e(money($item['unit_price'])) ?></td>
                        <td><?= (int) $item['quantity'] ?></td>
                        <td><?= e(money((float) $item['unit_price'] * (int) $item['quantity'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th colspan="3">Order total</th><th><?= e(money($order['total'])) ?></th></tr></tfoot>
            </table>
        </div>
    </section>
    <aside class="admin-panel order-customer-panel">
        <div class="admin-panel-heading"><h2>Customer</h2></div>
        <dl class="order-customer-details">
            <dt>Name</dt><dd><?= e($order['customer_name']) ?></dd>
            <dt>Email</dt><dd><a href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a></dd>
            <dt>Phone</dt><dd><a href="tel:<?= e($order['phone']) ?>"><?= e($order['phone']) ?></a></dd>
            <dt>Delivery address</dt><dd><?= nl2br(e($order['address']), false) ?></dd>
            <dt>Placed</dt><dd><?= e(date('M j, Y H:i', strtotime($order['created_at']))) ?></dd>
        </dl>
        <?php if ($nextStatuses || $canCancel): ?>
            <form class="order-status-form" method="post" action="<?= e(url('admin/order.php?id=' . (int) $order['id'])) ?>">
                <?= csrf_field() ?>
                <label for="status">Change status</label>
                <select id="status" name="status" required>
                    <option value="">Choose status</option>
                    <?php foreach ($nextStatuses as $nextStatus): ?>
                        <option value="<?= e($nextStatus) ?>"><?= e(ucfirst($nextStatus)) ?></option>
                    <?php endforeach; ?>
                    <?php if ($canCancel): ?><option value="cancelled">Cancel order</option><?php endif; ?>
                </select>
                <button class="admin-button" type="submit">Save status</button>
                <?php if ($canCancel): ?><small>Cancellation restores all ordered quantities to stock.</small><?php endif; ?>
            </form>
        <?php endif; ?>
    </aside>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
