<?php
session_start();

// 認証チェック
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $newPassword = $_POST['edit_password'] ?? '';

    if (empty($username) || empty($newPassword)) {
        header('Location: index.php');
        exit();
    }

    $users = json_decode(file_get_contents('../data/users.json'), true);

    if (isset($users[$username])) {
        $users[$username]['password'] = $newPassword; // 平文で管理
        $users[$username]['force_reset'] = true;
        file_put_contents('../data/users.json', json_encode($users, JSON_PRETTY_PRINT));

        // ログ記録
        $logs = json_decode(file_get_contents('../data/logs.json'), true);
        $logs[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => 'admin',
            'action' => 'ユーザー編集（パスワード更新）: ' . $username
        ];
        file_put_contents('../data/logs.json', json_encode($logs, JSON_PRETTY_PRINT));

        header('Location: index.php');
        exit();
    } else {
        header('Location: index.php');
        exit();
    }
} else {
    header('Location: index.php');
    exit();
}
?>
