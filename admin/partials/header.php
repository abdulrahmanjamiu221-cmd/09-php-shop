<?php
declare(strict_types=1);

$adminFlash = get_flash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | Good Goods Admin</title>
    <link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/admin.css')) ?>">
</head>
<body class="admin-body">
<header class="admin-header">
    <div class="container admin-header-inner">
        <a class="brand" href="<?= e(url('admin/index.php')) ?>"><span class="brand-mark">g.</span> good goods <span class="admin-label">ADMIN</span></a>
        <nav class="admin-nav" aria-label="Admin navigation">
            <a href="<?= e(url('admin/index.php')) ?>">Overview</a>
            <a href="<?= e(url('admin/orders.php')) ?>">Orders</a>
            <a href="<?= e(url()) ?>" target="_blank" rel="noopener">View shop</a>
            <form method="post" action="<?= e(url('admin/logout.php')) ?>">
                <?= csrf_field() ?>
                <button type="submit">Sign out</button>
            </form>
        </nav>
    </div>
</header>
<main class="container admin-main">
    <div class="admin-welcome">Signed in as <?= e(admin_name()) ?></div>
    <?php if ($adminFlash): ?>
        <div class="admin-alert" role="status"><?= e($adminFlash['message']) ?></div>
    <?php endif; ?>
