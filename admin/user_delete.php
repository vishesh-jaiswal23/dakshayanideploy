<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../index.php';

$user_id = isset($_GET['id']) ? $_GET['id'] : null;

if ($user_id) {
    $users = readJsonFile(USERS_FILE, []);
    $updated_users = [];
    foreach ($users as $user) {
        if ($user['id'] !== $user_id) {
            $updated_users[] = $user;
        }
    }
    writeJsonFile(USERS_FILE, $updated_users);
}

header('Location: /admin/users.php');
exit;
