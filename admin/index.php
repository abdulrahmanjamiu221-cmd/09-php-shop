<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$salesStatement = $pdo->query(
    "SELECT COALESCE(SUM(total), 0)
     FROM orders
     WHERE DATE(created_at) = CURRENT_DATE
       AND status IN ('paid', 'shipped', 'delivered')"
);
$todaySales = (float) $salesStatement->fetchColumn();

$statusStatement = $pdo->query(
    'SELECT status, COUNT(*) AS order_count, COALESCE(SUM(total), 0) AS order_total
     FROM orders GROUP BY status
     ORDER BY FIELD(status, \'pending\', \'paid\', \'shipped\', \'delivered\', \'cancelled\')'
);
$statusTotals = [];
foreach (['pending', 'paid', 'shipped', 'delivered', 'cancelled'] as $status) {
    $statusTotals[$status] = ['order_count' => 0, 'order_total' => 0];
}
foreach ($statusStatement->fetchAll() as $row) {
    $statusTotals[$row['status']] = $row;
}

$topProductsStatement = $pdo->query(
    "SELECT MAX(oi.product_name) AS product_name, SUM(oi.quantity) AS quantity_sold
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     WHERE o.status IN ('paid', 'shipped', 'delivered')
     GROUP BY oi.product_id
     ORDER BY quantity_sold DESC, oi.product_name ASC
     LIMIT 5"
);
$topProducts = $topProductsStatement->fetchAll();

$recentOrdersStatement = $pdo->query(
    'SELECT o.id, o.reference, o.customer_name, o.total, o.status, o.created_at,
            COALESCE(SUM(oi.quantity), 0) AS item_count
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     GROUP BY o.id, o.reference, o.customer_name, o.total, o.status, o.created_at
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT 8'
);
$recentOrders = $recentOrdersStatement->fetchAll();

$pageTitle = 'Overview';
require __DIR__ . '/partials/header.php';
?>
<section class="admin-page-heading">
    <div><p class="eyebrow">Store performance</p><h1>Overview</h1></div>
    <a class="admin-button" href="<?= e(url('admin/orders.php')) ?>">View all orders <span aria-hidden="true">→</span></a>
</section>
<section class="admin-stat-grid" aria-label="Today's sales">
    <article class="admin-stat-card admin-stat-feature">
        <span>Today's sales</span>
        <strong><?= e(money($todaySales)) ?></strong>
        <small>Paid, shipped and delivered orders</small>
    </article>
    <?php foreach ($statusTotals as $status => $row): ?>
        <a class="admin-stat-card" href="<?= e(url('admin/orders.php?status=' . rawurlencode($status))) ?>">
            <span><?= e(ucfirst($status)) ?></span>
            <strong><?= (int) $row['order_count'] ?></strong>
            <small><?= e(money($row['order_total'])) ?> order value</small>
        </a>
    <?php endforeach; ?>
</section>
<div class="admin-dashboard-grid">
    <section class="admin-panel">
        <div class="admin-panel-heading"><h2>Recent orders</h2><a href="<?= e(url('admin/orders.php')) ?>">All orders →</a></div>
        <?php if ($recentOrders): ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td><a class="admin-order-ref" href="<?= e(url('admin/order.php?id=' . (int) $order['id'])) ?>"><?= e($order['reference']) ?></a><small><?= e(date('M j, Y', strtotime($order['created_at']))) ?></small></td>
                            <td><?= e($order['customer_name']) ?></td>
                            <td><?= (int) $order['item_count'] ?></td>
                            <td><?= e(money($order['total'])) ?></td>
                            <td><span class="status-pill status-<?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="admin-empty">Orders will appear here once customers check out.</p>
        <?php endif; ?>
    </section>
    <section class="admin-panel">
        <div class="admin-panel-heading"><h2>Top products</h2><span>By units sold</span></div>
        <?php if ($topProducts): ?>
            <ol class="top-products">
                <?php foreach ($topProducts as $index => $product): ?>
                    <li><span class="top-product-rank"><?= $index + 1 ?></span><span class="top-product-name"><?= e($product['product_name']) ?></span><strong><?= (int) $product['quantity_sold'] ?></strong></li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <p class="admin-empty">No completed sales yet.</p>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
