<?php
declare(strict_types=1);

function cart_add(int $id, int $qty): string
{
    if ($id < 1 || $qty < 1) {
        throw new InvalidArgumentException('Product and quantity must be positive.');
    }

    $cart = $_SESSION['cart'] ?? [];
    $currentQuantity = 0;
    if (is_array($cart) && isset($cart[$id])) {
        $validQuantity = filter_var($cart[$id], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $currentQuantity = $validQuantity === false ? 0 : $validQuantity;
    }
    $message = cart_update($id, $currentQuantity + $qty);

    return $message === 'Bag quantity updated.' ? 'Added to your bag.' : $message;
}

function cart_update(int $id, int $qty): string
{
    if ($id < 1 || $qty < 1) {
        throw new InvalidArgumentException('Product and quantity must be positive.');
    }

    global $pdo;
    $statement = $pdo->prepare('SELECT stock, is_active FROM products WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $product = $statement->fetch();
    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart)) {
        $cart = [];
    }

    if (!$product || !(bool) $product['is_active'] || (int) $product['stock'] < 1) {
        unset($cart[$id]);
        $_SESSION['cart'] = $cart;
        return 'This item is no longer available and was removed from your bag.';
    }

    $stock = (int) $product['stock'];
    $cart[$id] = min($qty, $stock);
    $_SESSION['cart'] = $cart;

    return $qty > $stock
        ? 'Quantity adjusted to the available stock (' . $stock . ').'
        : 'Bag quantity updated.';
}

function cart_remove(int $id): void
{
    $cart = $_SESSION['cart'] ?? [];
    if (is_array($cart)) {
        unset($cart[$id]);
        $_SESSION['cart'] = $cart;
    }
}

function cart_clear(): void
{
    $_SESSION['cart'] = [];
}

function cart_items(): array
{
    global $pdo;
    $storedCart = $_SESSION['cart'] ?? [];
    $cart = [];
    if (is_array($storedCart)) {
        foreach ($storedCart as $id => $quantity) {
            $validId = filter_var((string) $id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $validQuantity = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($validId !== false && $validQuantity !== false) {
                $cart[(int) $validId] = (int) $validQuantity;
            }
        }
    }

    if (!$cart) {
        $_SESSION['cart'] = [];
        return [];
    }

    $ids = array_keys($cart);
    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $statement = $pdo->prepare(
        "SELECT id, name, slug, price, stock, image_url, is_active
         FROM products WHERE id IN ({$placeholders})"
    );
    $statement->execute($ids);

    $products = [];
    foreach ($statement->fetchAll() as $product) {
        $products[(int) $product['id']] = $product;
    }

    $lines = [];
    $notices = [];
    foreach ($cart as $id => $quantity) {
        $product = $products[$id] ?? null;
        if (!$product || !(bool) $product['is_active'] || (int) $product['stock'] < 1) {
            unset($cart[$id]);
            $notices[] = 'An unavailable item was removed from your bag.';
            continue;
        }

        $stock = (int) $product['stock'];
        $adjusted = $quantity > $stock;
        if ($adjusted) {
            $quantity = $stock;
            $cart[$id] = $quantity;
            $notices[] = $product['name'] . ' quantity was adjusted to the available stock (' . $stock . ').';
        }

        $unitPriceCents = (int) round((float) $product['price'] * 100);
        $product['quantity'] = $quantity;
        $product['line_total'] = ($unitPriceCents * $quantity) / 100;
        $product['stock_ok'] = $quantity <= $stock;
        $product['stock_adjusted'] = $adjusted;
        $lines[] = $product;
    }

    $_SESSION['cart'] = $cart;
    if ($notices) {
        set_flash('notice', implode(' ', $notices));
    }

    return $lines;
}

function cart_total(): float
{
    $totalCents = 0;
    foreach (cart_items() as $item) {
        $unitPriceCents = (int) round((float) $item['price'] * 100);
        $totalCents += $unitPriceCents * (int) $item['quantity'];
    }

    return $totalCents / 100;
}

function cart_count(): int
{
    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart)) {
        return 0;
    }

    $count = 0;
    foreach ($cart as $id => $quantity) {
        $validId = filter_var((string) $id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $validQuantity = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($validId !== false && $validQuantity !== false) {
            $count += $validQuantity;
        }
    }
    return $count;
}
