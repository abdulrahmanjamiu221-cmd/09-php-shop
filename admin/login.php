<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (isset($_SESSION['admin_user_id']) && is_int($_SESSION['admin_user_id'])) {
    header('Location: ' . url('admin/index.php'));
    exit;
}

$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $usernameInput = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $username = is_string($usernameInput) ? trim($usernameInput) : '';
    $password = is_string($password) ? $password : '';
    $statement = $pdo->prepare(
        'SELECT id, username, password_hash FROM admin_users WHERE username = :username LIMIT 1'
    );
    $statement->execute(['username' => substr($username, 0, 80)]);
    $admin = $statement->fetch();
    $hash = is_array($admin)
        ? (string) $admin['password_hash']
        : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    $passwordIsValid = password_verify($password, $hash);

    if (!is_array($admin) || !$passwordIsValid) {
        $error = 'Invalid username or password.';
    } else {
        session_regenerate_id(true);
        $_SESSION['admin_user_id'] = (int) $admin['id'];
        $_SESSION['admin_name'] = (string) $admin['username'];
        header('Location: ' . url('admin/index.php'));
        exit;
    }
}

$flash = get_flash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin sign in | Good Goods</title>
    <link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/admin.css')) ?>">
</head>
<body class="admin-body">
<main class="admin-login-wrap">
    <section class="admin-login-card">
        <a class="brand" href="<?= e(url()) ?>"><span class="brand-mark">g.</span> good goods</a>
        <p class="eyebrow">Store administration</p>
        <h1>Welcome back</h1>
        <?php if ($flash): ?>
            <div class="admin-alert" role="status"><?= e($flash['message']) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="admin-alert admin-alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('admin/login.php')) ?>">
            <?= csrf_field() ?>
            <label for="username">Username</label>
            <input id="username" name="username" type="text" maxlength="80" autocomplete="username" value="<?= e($username) ?>" required>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            <button class="button button-dark" type="submit">Sign in</button>
        </form>
        <a class="admin-back-link" href="<?= e(url()) ?>">Back to shop</a>
    </section>
</main>
</body>
</html>
