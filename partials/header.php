<?php
$flash = get_flash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8f6f1">
    <title><?= e($pageTitle) ?> | Good Goods</title>
    <link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url()) ?>" aria-label="Good Goods home">
            <span class="brand-mark">g.</span> good goods
        </a>
        <nav class="site-nav" aria-label="Main navigation">
            <a href="<?= e(url()) ?>">Shop</a>
            <a href="<?= e(url('order-lookup.php')) ?>">Order lookup</a>
            <a class="cart-link" href="<?= e(url('cart.php')) ?>">
                Bag <span class="cart-count"><?= cart_count() ?></span>
            </a>
        </nav>
    </div>
</header>
<main class="container site-main">
    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endif; ?>
