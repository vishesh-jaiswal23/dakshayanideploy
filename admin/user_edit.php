<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../index.php';

$users = readJsonFile(USERS_FILE, []);
$user_id = isset($_GET['id']) ? $_GET['id'] : null;
$user = null;

if ($user_id) {
    foreach ($users as $u) {
        if ($u['id'] === $user_id) {
            $user = $u;
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_user_data = [
        'id' => $user_id ? $user_id : uniqid('user_', true),
        'name' => isset($_POST['name']) ? $_POST['name'] : '',
        'email' => isset($_POST['email']) ? $_POST['email'] : '',
        'role' => isset($_POST['role']) ? $_POST['role'] : 'customer',
    ];

    if (isset($_POST['password']) && !empty($_POST['password'])) {
        $new_user_data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
    }

    if ($user_id) {
        foreach ($users as &$u) {
            if ($u['id'] === $user_id) {
                $u = array_merge($u, $new_user_data);
                break;
            }
        }
    } else {
        $users[] = $new_user_data;
    }

    writeJsonFile(USERS_FILE, $users);

    header('Location: /admin/users.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $user ? 'Edit User' : 'Create User'; ?></title>
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <header>
            <h1><?php echo $user ? 'Edit User' : 'Create User'; ?></h1>
            <a href="/admin/users.php">Back to User Management</a>
        </header>
        <main>
            <form method="post">
                <div>
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" <?php echo $user ? '' : 'required'; ?>>
                    <?php if ($user): ?>
                        <small>Leave blank to keep the current password.</small>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="role">Role</label>
                    <select id="role" name="role">
                        <option value="customer" <?php echo ($user['role'] ?? 'customer') === 'customer' ? 'selected' : ''; ?>>Customer</option>
                        <option value="admin" <?php echo ($user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <button type="submit"><?php echo $user ? 'Update User' : 'Create User'; ?></button>
            </form>
        </main>
    </div>
</body>
</html>
