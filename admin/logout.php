<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

verify_csrf();
unset($_SESSION['admin_user_id'], $_SESSION['admin_name']);
session_regenerate_id(true);
set_flash('success', 'You have been signed out.');
header('Location: ' . url('admin/login.php'));
exit;
