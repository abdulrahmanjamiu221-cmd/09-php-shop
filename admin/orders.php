<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$statuses = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];
$status = isset($_GET['status']) && is_string($_GET['status']) ? $_GET['status'] : '';
$from = isset($_GET['from']) && is_string($_GET['from']) ? $_GET['from'] : '';
$to = isset($_GET['to']) && is_string($_GET['to']) ? $_GET['to'] : '';
$filters = [];
$parameters = [];
if ($status !== '' && in_array($status, $statuses, true)) {
    $filters[] = 'o.status = :status';
    $parameters['status'] = $status;
} else {
    $status = '';
}
foreach (['from' => $from, 'to' => $to] as $field => $date) {
    if ($date !== '') {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if ($parsed === false || ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count'])) || $parsed->format('Y-m-d') !== $date) {
            if ($field === 'from') {
                $from = '';
            } else {
                $to = '';
            }
        }
    }
}
if ($from !== '') {
    $filters[] = 'o.created_at >= :date_from';
    $parameters['date_from'] = $from . ' 00:00:00';
}
if ($to !== '') {
    $filters[] = 'o.created_at < :date_to';
    $parameters['date_to'] = (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
}

$sql = 'SELECT o.id, o.reference, o.customer_name, o.email, o.total, o.status, o.created_at,
               COALESCE(SUM(oi.quantity), 0) AS item_count,
               COUNT(oi.product_id) AS line_count
        FROM orders o
        LEFT JOIN order_items oi ON oi.order_id = o.id';
if ($filters) {
    $sql .= ' WHERE ' . implode(' AND ', $filters);
}
$sql .= ' GROUP BY o.id, o.reference, o.customer_name, o.email, o.total, o.status, o.created_at
          ORDER BY o.created_at DESC, o.id DESC';
$statement = $pdo->prepare($sql);
$statement->execute($parameters);
$orders = $statement->fetchAll();

$visibleTotal = 0.0;
foreach ($orders as $order) {
    $visibleTotal += (float) $order['total'];
}

$pageTitle = 'Orders';
require __DIR__ . '/partials/header.php';
?>
<section class="admin-page-heading">
    <div><p class="eyebrow">Store administration</p><h1>Orders</h1></div>
    <span class="admin-result-count"><?= count($orders) ?> orders · <?= e(money($visibleTotal)) ?></span>
</section>
<form class="admin-filters" method="get" action="<?= e(url('admin/orders.php')) ?>">
    <label>Status
        <select name="status">
            <option value="">All statuses</option>
            <?php foreach ($statuses as $option): ?>
                <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>From
        <input type="date" name="from" value="<?= e($from) ?>">
    </label>
    <label>To
        <input type="date" name="to" value="<?= e($to) ?>">
    </label>
    <button class="admin-button" type="submit">Apply filters</button>
    <?php if ($status !== '' || $from !== '' || $to !== ''): ?><a class="admin-clear-link" href="<?= e(url('admin/orders.php')) ?>">Clear</a><?php endif; ?>
</form>
<section class="admin-panel">
    <?php if ($orders): ?>
        <div class="admin-table-wrap">
            <table class="admin-table orders-table">
                <thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Lines</th><th>Total</th><th>Status</th><th>Placed</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><a class="admin-order-ref" href="<?= e(url('admin/order.php?id=' . (int) $order['id'])) ?>"><?= e($order['reference']) ?></a></td>
                        <td><?= e($order['customer_name']) ?><small><?= e($order['email']) ?></small></td>
                        <td><?= (int) $order['item_count'] ?></td>
                        <td><?= (int) $order['line_count'] ?></td>
                        <td><?= e(money($order['total'])) ?></td>
                        <td><span class="status-pill status-<?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span></td>
                        <td><?= e(date('M j, Y H:i', strtotime($order['created_at']))) ?></td>
                        <td><a class="admin-row-link" href="<?= e(url('admin/order.php?id=' . (int) $order['id'])) ?>">Details →</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="admin-empty">No orders match these filters.</p>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
