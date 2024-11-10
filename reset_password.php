<?php
session_start();

// 認証チェック
if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit();
}

$username = $_SESSION['username'];

// 初回ログイン時のみアクセス可能
$users = json_decode(file_get_contents('data/users.json'), true);
if (!$users[$username]['force_reset']) {
    header('Location: index.php');
    exit();
}

$errors = [];
$success = false;

// パスワード変更処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($newPassword) || empty($confirmPassword)) {
        $errors[] = 'すべてのフィールドを入力してください。';
    } elseif ($newPassword !== $confirmPassword) {
        $errors[] = 'パスワードが一致しません。';
    } else {
        // パスワードを更新
        $users[$username]['password'] = password_hash($newPassword, PASSWORD_BCRYPT);
        $users[$username]['force_reset'] = false;
        file_put_contents('data/users.json', json_encode($users, JSON_PRETTY_PRINT));

        // ログ記録
        $logs = json_decode(file_get_contents('data/logs.json'), true);
        $logs[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => $username,
            'action' => 'パスワード変更'
        ];
        file_put_contents('data/logs.json', json_encode($logs, JSON_PRETTY_PRINT));

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>パスワード変更 - サムネイル付きリンク生成サービス</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* リセットCSS */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        /* 共通スタイル */
        body {
            background-color: #121212;
            color: #ffffff;
            font-family: 'Helvetica Neue', Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background-color: #1e1e1e;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            animation: fadeIn 1s ease-in-out;
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
            font-size: 16px;
        }
        input[type="password"] {
            width: 100%;
            background-color: #2a2a2a;
            color: #ffffff;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            padding: 10px;
            margin-top: 5px;
            transition: background-color 0.3s;
        }
        input[type="password"]:focus {
            background-color: #3a3a3a;
            outline: none;
        }
        button {
            background: linear-gradient(to right, #00e5ff, #00b0ff);
            color: #000;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            padding: 15px;
            margin-top: 20px;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s, background 0.3s;
        }
        button:hover {
            background: linear-gradient(to right, #00b0ff, #00e5ff);
            transform: scale(1.02);
        }
        .error {
            color: #ff5252;
            font-size: 14px;
            animation: shake 0.5s;
            margin-top: 10px;
            text-align: center;
        }
        .success-message {
            background-color: #1e1e1e;
            color: #ffffff;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            animation: fadeInUp 0.5s;
            text-align: center;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes shake {
            0% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            50% { transform: translateX(5px); }
            75% { transform: translateX(-5px); }
            100% { transform: translateX(0); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>パスワード変更</h1>
        <?php if ($success): ?>
            <div class="success-message">
                <p>パスワードが正常に変更されました。</p>
                <a href="index.php" style="color: #00e5ff;">ログインページへ</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <label for="new_password">新しいパスワード</label>
                <input type="password" id="new_password" name="new_password" required>

                <label for="confirm_password">パスワード確認</label>
                <input type="password" id="confirm_password" name="confirm_password" required>

                <button type="submit">パスワード変更</button>
            </form>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
