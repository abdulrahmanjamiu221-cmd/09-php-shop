<?php
declare(strict_types=1);

function require_admin(): void
{
    if (!isset($_SESSION['admin_user_id']) || !is_int($_SESSION['admin_user_id'])) {
        header('Location: ' . url('admin/login.php'));
        exit;
    }
}

function admin_name(): string
{
    $name = $_SESSION['admin_name'] ?? 'Administrator';
    return is_string($name) ? $name : 'Administrator';
}
