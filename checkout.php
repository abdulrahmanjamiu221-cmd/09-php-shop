<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$form = [
    'customer_name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
];
$errors = [];
$cartAtRequest = $_SESSION['cart'] ?? [];
$items = cart_items();

if (!$items) {
    set_flash('error', 'Your bag is empty. Add an item before checking out.');
    header('Location: ' . url('cart.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $cartBeforeRefresh = $_SESSION['cart'] ?? [];
    foreach ($form as $field => $default) {
        $value = $_POST[$field] ?? '';
        $form[$field] = is_string($value) ? trim($value) : $default;
    }

    if ($form['customer_name'] === '' || strlen($form['customer_name']) > 120) {
        $errors['customer_name'] = 'Enter your name (up to 120 characters).';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || strlen($form['email']) > 160) {
        $errors['email'] = 'Enter a valid email address (up to 160 characters).';
    }
    if ($form['phone'] === '' || strlen($form['phone']) > 20) {
        $errors['phone'] = 'Enter a phone number (up to 20 characters).';
    }
    if ($form['address'] === '' || strlen($form['address']) > 65535) {
        $errors['address'] = 'Enter a delivery address.';
    }

    if (!$errors) {
        $items = cart_items();
        if (!$items || $_SESSION['cart'] !== $cartAtRequest) {
            set_flash('error', 'Stock changed. Your bag was updated; review the available quantities before trying again.');
            header('Location: ' . url('cart.php'));
            exit;
        }

        $checkoutItems = $items;
        usort($checkoutItems, static fn (array $left, array $right): int => (int) $left['id'] <=> (int) $right['id']);
        $stockConflict = false;

        try {
            $pdo->beginTransaction();

            $lockStatement = $pdo->prepare(
                'SELECT id, name, price, stock, is_active FROM products WHERE id = ? FOR UPDATE'
            );
            $orderLines = [];
            $totalCents = 0;
            foreach ($checkoutItems as $item) {
                $lockStatement->execute([(int) $item['id']]);
                $product = $lockStatement->fetch();
                if (!$product || !(bool) $product['is_active'] || (int) $product['stock'] < (int) $item['quantity']) {
                    $stockConflict = true;
                    throw new RuntimeException('Product stock changed during checkout.');
                }

                $unitPrice = number_format((float) $product['price'], 2, '.', '');
                $unitPriceCents = (int) round((float) $unitPrice * 100);
                $quantity = (int) $item['quantity'];
                $totalCents += $unitPriceCents * $quantity;
                $orderLines[] = [
                    'id' => (int) $product['id'],
                    'name' => (string) $product['name'],
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                ];
            }

            $orderStatement = $pdo->prepare(
                'INSERT INTO orders (reference, customer_name, email, phone, address, total, status)
                 VALUES (:reference, :customer_name, :email, :phone, :address, :total, :status)'
            );
            $reference = '';
            $orderInserted = false;
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $reference = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
                try {
                    $orderStatement->execute([
                        'reference' => $reference,
                        'customer_name' => $form['customer_name'],
                        'email' => $form['email'],
                        'phone' => $form['phone'],
                        'address' => $form['address'],
                        'total' => number_format($totalCents / 100, 2, '.', ''),
                        'status' => 'pending',
                    ]);
                    $orderInserted = true;
                    break;
                } catch (PDOException $exception) {
                    if ((int) ($exception->errorInfo[1] ?? 0) !== 1062 || $attempt === 4) {
                        throw $exception;
                    }
                }
            }
            if (!$orderInserted) {
                throw new RuntimeException('Could not generate an order reference.');
            }

            $orderId = (int) $pdo->lastInsertId();
            $itemStatement = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity)
                 VALUES (:order_id, :product_id, :product_name, :unit_price, :quantity)'
            );
            $stockStatement = $pdo->prepare(
                'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?'
            );

            foreach ($orderLines as $line) {
                $itemStatement->execute([
                    'order_id' => $orderId,
                    'product_id' => $line['id'],
                    'product_name' => $line['name'],
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                ]);
                $stockStatement->execute([$line['quantity'], $line['id'], $line['quantity']]);
                if ($stockStatement->rowCount() !== 1) {
                    $stockConflict = true;
                    throw new RuntimeException('Product stock changed during checkout.');
                }
            }

            $pdo->commit();
            $_SESSION['last_order_reference'] = $reference;
            cart_clear();
            header('Location: ' . url('order-success.php?ref=' . rawurlencode($reference)));
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($stockConflict) {
                cart_items();
                set_flash('error', 'Stock changed while you were checking out. Review your bag and try again.');
                header('Location: ' . url('cart.php'));
                exit;
            }

            error_log('Shop checkout failed: ' . $exception->getMessage());
            $items = cart_items();
            set_flash('error', 'We could not place your order just now. No order was placed; please try again.');
        }
    }
}

$subtotalCents = 0;
foreach ($items as $item) {
    $subtotalCents += (int) round((float) $item['price'] * 100) * (int) $item['quantity'];
}
$subtotal = $subtotalCents / 100;

$pageTitle = 'Checkout';
require __DIR__ . '/partials/header.php';
?>
<section class="checkout-page">
    <p class="eyebrow">Almost yours</p>
    <h1>Checkout</h1>
    <p class="checkout-lead">Add your delivery details to place a pending order. No payment is collected here.</p>
    <div class="checkout-layout">
        <form class="checkout-form" method="post" action="<?= e(url('checkout.php')) ?>">
            <?= csrf_field() ?>
            <label for="customer_name">Full name</label>
            <input id="customer_name" name="customer_name" type="text" maxlength="120" autocomplete="name" value="<?= e($form['customer_name']) ?>" required>
            <?php if (isset($errors['customer_name'])): ?><span class="field-error"><?= e($errors['customer_name']) ?></span><?php endif; ?>

            <label for="email">Email address</label>
            <input id="email" name="email" type="email" maxlength="160" autocomplete="email" value="<?= e($form['email']) ?>" required>
            <?php if (isset($errors['email'])): ?><span class="field-error"><?= e($errors['email']) ?></span><?php endif; ?>

            <label for="phone">Phone number</label>
            <input id="phone" name="phone" type="tel" maxlength="20" autocomplete="tel" value="<?= e($form['phone']) ?>" required>
            <?php if (isset($errors['phone'])): ?><span class="field-error"><?= e($errors['phone']) ?></span><?php endif; ?>

            <label for="address">Delivery address</label>
            <textarea id="address" name="address" rows="4" autocomplete="street-address" required><?= e($form['address']) ?></textarea>
            <?php if (isset($errors['address'])): ?><span class="field-error"><?= e($errors['address']) ?></span><?php endif; ?>

            <button class="button button-dark" type="submit">Place pending order <span aria-hidden="true">→</span></button>
        </form>
        <aside class="checkout-summary">
            <h2>Order summary</h2>
            <?php foreach ($items as $item): ?>
                <p><span><?= e($item['name']) ?> × <?= (int) $item['quantity'] ?></span><strong><?= e(money($item['line_total'])) ?></strong></p>
            <?php endforeach; ?>
            <p class="checkout-subtotal"><span>Subtotal</span><strong><?= e(money($subtotal)) ?></strong></p>
            <span class="summary-note">Prices and stock are checked again when the order is placed.</span>
        </aside>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
