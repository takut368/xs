<?php
// セッションの開始
session_start();

// 管理者ログインの固定
define('ADMIN_ID', 'admin');
define('ADMIN_PASSWORD', 'admin');

// ディレクトリとファイルの自動作成
$directories = ['uploads', 'temp', 'admin', 'data'];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// 必要なJSONファイルの初期化
$data_files = [
    'data/users.json' => json_encode([]),
    'data/seisei.json' => json_encode([]),
    'data/logs.json' => json_encode([]),
];
foreach ($data_files as $file => $content) {
    if (!file_exists($file)) {
        file_put_contents($file, $content);
    }
}

// CSRFトークン生成
if (empty($_SESSION['admin_token'])) {
    $_SESSION['admin_token'] = bin2hex(random_bytes(32));
}

// エラーメッセージと成功メッセージの初期化
$errors = [];
$success = '';
$action = $_GET['action'] ?? 'dashboard';

// 管理者がログインしているか確認
if (!isset($_SESSION['admin'])) {
    // ログインフォームからの送信
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
        // CSRFトークンの検証
        if (!hash_equals($_SESSION['admin_token'], $_POST['token'])) {
            $errors[] = '不正なリクエストです。';
        } else {
            $username = trim($_POST['username']);
            $password = $_POST['password'];

            if ($username === ADMIN_ID && $password === ADMIN_PASSWORD) {
                $_SESSION['admin'] = true;
                header('Location: index.php');
                exit();
            } else {
                $errors[] = '無効なIDまたはパスワードです。';
            }
        }
    }
} else {
    // 管理者がログインしている場合の処理
    // POSTリクエストの処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRFトークンの検証
        if (!hash_equals($_SESSION['admin_token'], $_POST['token'])) {
            $errors[] = '不正なリクエストです。';
        } else {
            // ユーザー管理
            if (isset($_POST['add_user'])) {
                $newUserId = trim($_POST['new_user_id']);
                $newUserPassword = $_POST['new_user_password'];

                if (empty($newUserId) || empty($newUserPassword)) {
                    $errors[] = 'ユーザーIDとパスワードを入力してください。';
                } else {
                    $users = json_decode(file_get_contents('../data/users.json'), true);
                    // ユーザーIDの重複確認
                    foreach ($users as $user) {
                        if ($user['id'] === $newUserId) {
                            $errors[] = '既に存在するユーザーIDです。';
                            break;
                        }
                    }
                    if (empty($errors)) {
                        $hashedPassword = password_hash($newUserPassword, PASSWORD_DEFAULT);
                        $users[] = [
                            'id' => $newUserId,
                            'password' => $hashedPassword,
                            'created_at' => date('Y-m-d H:i:s'),
                        ];
                        file_put_contents('../data/users.json', json_encode($users, JSON_PRETTY_PRINT));
                        $success = 'ユーザーを追加しました。';
                        logAction('admin', 'add_user', $newUserId);
                    }
                }
            }

            // ユーザー削除
            if (isset($_POST['delete_user'])) {
                $deleteUserId = $_POST['delete_user_id'];
                $users = json_decode(file_get_contents('../data/users.json'), true);
                $updatedUsers = [];
                $found = false;
                foreach ($users as $user) {
                    if ($user['id'] === $deleteUserId) {
                        $found = true;
                        continue;
                    }
                    $updatedUsers[] = $user;
                }
                if ($found) {
                    file_put_contents('../data/users.json', json_encode($updatedUsers, JSON_PRETTY_PRINT));
                    $success = 'ユーザーを削除しました。';
                    logAction('admin', 'delete_user', $deleteUserId);
                } else {
                    $errors[] = 'ユーザーが見つかりません。';
                }
            }

            // ユーザー編集
            if (isset($_POST['edit_user'])) {
                $editUserId = $_POST['edit_user_id'];
                $editUserPassword = $_POST['edit_user_password'];
                $users = json_decode(file_get_contents('../data/users.json'), true);
                $updated = false;
                foreach ($users as &$user) {
                    if ($user['id'] === $editUserId) {
                        if (!empty($editUserPassword)) {
                            $user['password'] = password_hash($editUserPassword, PASSWORD_DEFAULT);
                        }
                        $updated = true;
                        break;
                    }
                }
                if ($updated) {
                    file_put_contents('../data/users.json', json_encode($users, JSON_PRETTY_PRINT));
                    $success = 'ユーザーを編集しました。';
                    logAction('admin', 'edit_user', $editUserId);
                } else {
                    $errors[] = 'ユーザーが見つかりません。';
                }
            }

            // パスワードリセット
            if (isset($_POST['reset_password'])) {
                $resetUserId = $_POST['reset_user_id'];
                $newPassword = bin2hex(random_bytes(4)); // ランダムなパスワード生成
                $users = json_decode(file_get_contents('../data/users.json'), true);
                $updated = false;
                foreach ($users as &$user) {
                    if ($user['id'] === $resetUserId) {
                        $user['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                        $updated = true;
                        break;
                    }
                }
                if ($updated) {
                    file_put_contents('../data/users.json', json_encode($users, JSON_PRETTY_PRINT));
                    $success = 'ユーザーのパスワードをリセットしました。';
                    logAction('admin', 'reset_password', $resetUserId);
                    // リセットされたパスワードを表示
                    echo "<script>alert('新しいパスワード: {$newPassword}');</script>";
                } else {
                    $errors[] = 'ユーザーが見つかりません。';
                }
            }

            // リンクの削除
            if (isset($_POST['delete_link'])) {
                $deleteLinkId = $_POST['delete_link_id'];
                $userId = $_POST['delete_link_user_id'];
                $seisei = json_decode(file_get_contents('../data/seisei.json'), true);
                if (isset($seisei[$userId][$deleteLinkId])) {
                    unset($seisei[$userId][$deleteLinkId]);
                    file_put_contents('../data/seisei.json', json_encode($seisei, JSON_PRETTY_PRINT));
                    // フォルダの削除
                    $dirPath = '../' . $deleteLinkId;
                    if (is_dir($dirPath)) {
                        // フォルダ内のファイルを削除
                        $files = glob($dirPath . '/*');
                        foreach ($files as $file) {
                            if (is_file($file)) {
                                unlink($file);
                            }
                        }
                        rmdir($dirPath);
                    }
                    $success = 'リンクを削除しました。';
                    logAction('admin', 'delete_link', $deleteLinkId);
                } else {
                    $errors[] = 'リンクが見つかりません。';
                }
            }
        }
    }
}

// ログ記録関数
function logAction($user, $action, $detail = '')
{
    $logs = json_decode(file_get_contents('../data/logs.json'), true);
    $logs[] = [
        'user' => $user,
        'action' => $action,
        'detail' => $detail,
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
    ];
    file_put_contents('../data/logs.json', json_encode($logs, JSON_PRETTY_PRINT));
}

// ユーザー情報の取得
$users = json_decode(file_get_contents('../data/users.json'), true);

// リンク情報の取得
$seisei = json_decode(file_get_contents('../data/seisei.json'), true);

// ログ情報の取得
$logs = json_decode(file_get_contents('../data/logs.json'), true);

// 初回ログイン時のパスワード変更処理は管理者によるリセットパスワード機能で対応
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>管理者画面 - サムネイル付きリンク生成サービス</title>
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
            background-color: #1e1e1e;
            color: #ffffff;
            font-family: 'Helvetica Neue', Arial, sans-serif;
            overflow-x: hidden;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            animation: fadeIn 1s ease-in-out;
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
        }
        nav {
            margin-bottom: 20px;
            text-align: center;
        }
        nav a {
            background: linear-gradient(to right, #00e5ff, #00b0ff);
            color: #000;
            padding: 10px 20px;
            margin: 5px;
            text-decoration: none;
            border-radius: 5px;
            transition: transform 0.2s;
        }
        nav a:hover {
            background: linear-gradient(to right, #00b0ff, #00e5ff);
            transform: scale(1.05);
        }
        .section {
            margin-bottom: 40px;
        }
        label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
            font-size: 16px;
        }
        input[type="text"],
        input[type="password"],
        textarea,
        input[type="url"],
        select {
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
        input[type="text"]:focus,
        input[type="password"]:focus,
        textarea:focus,
        input[type="url"]:focus,
        select:focus {
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
            transition: transform 0.2s;
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
        }
        .success {
            color: #4caf50;
            font-size: 14px;
            animation: fadeInUp 0.5s;
            margin-top: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table, th, td {
            border: 1px solid #555555;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #333333;
        }
        tr:nth-child(even) {
            background-color: #2a2a2a;
        }
        /* アニメーション */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes scaleUp {
            from { transform: scale(0.8); }
            to { transform: scale(1); }
        }
        @keyframes shake {
            0% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            50% { transform: translateX(5px); }
            75% { transform: translateX(-5px); }
            100% { transform: translateX(0); }
        }
        /* レスポンシブデザイン */
        @media screen and (max-width: 800px) {
            table, th, td {
                font-size: 14px;
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 初回アクセス時にid=123がない場合は画面を真っ白にする
            if (!window.location.search.includes('id=123')) {
                document.body.innerHTML = '';
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <?php if (!isset($_SESSION['admin'])): ?>
            <h1>管理者ログイン</h1>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['admin_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <label>ユーザーID</label>
                <input type="text" name="username" required>
                <label>パスワード</label>
                <input type="password" name="password" required>
                <button type="submit" name="admin_login">ログイン</button>
            </form>
        <?php else: ?>
            <h1>管理者画面</h1>
            <?php if (!empty($success)): ?>
                <div class="success">
                    <p><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <nav>
                <a href="index.php?action=dashboard">ダッシュボード</a>
                <a href="index.php?action=manage_users">ユーザー管理</a>
                <a href="index.php?action=logs">アクセスログ</a>
                <a href="index.php?action=logout">ログアウト</a>
            </nav>

            <?php
            switch ($action) {
                case 'manage_users':
                    // ユーザー管理セクション
                    ?>
                    <div class="section">
                        <h2>ユーザー管理</h2>
                        <h3>新規ユーザーの追加</h3>
                        <form method="POST">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['admin_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            <label>ユーザーID</label>
                            <input type="text" name="new_user_id" required>
                            <label>パスワード</label>
                            <input type="password" name="new_user_password" required>
                            <button type="submit" name="add_user">ユーザーを追加</button>
                        </form>

                        <h3>既存ユーザーの一覧</h3>
                        <table>
                            <tr>
                                <th>ユーザーID</th>
                                <th>作成日時</th>
                                <th>操作</th>
                            </tr>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($user['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline-block;">
                                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['admin_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="delete_user_id" value="<?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" name="delete_user">削除</button>
                                        </form>
                                        <button onclick="openEditModal('<?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?>')">編集</button>
                                        <form method="POST" style="display:inline-block;">
                                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['admin_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="reset_user_id" value="<?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" name="reset_password">パスワードリセット</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>

                        <!-- ユーザー編集モーダル -->
                        <div id="editModal" class="modal">
                            <div class="modal-content">
                                <span class="close" id="editClose">&times;</span>
                                <h2>ユーザー編集</h2>
                                <form method="POST">
                                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['admin_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" id="edit_user_id" name="edit_user_id">
                                    <label>新しいパスワード（変更しない場合は空白）</label>
                                    <input type="password" name="edit_user_password">
                                    <button type="submit" name="edit_user">変更を保存</button>
                                </form>
                            </div>
                        </div>

                        <script>
                            // ユーザー編集モーダルの処理
                            const editModal = document.getElementById('editModal');
                            const editClose = document.getElementById('editClose');
                            const editUserIdInput = document.getElementById('edit_user_id');

                            function openEditModal(userId) {
                                editUserIdInput.value = userId;
                                editModal.style.display = 'block';
                            }

                            editClose.addEventListener('click', function() {
                                editModal.style.display = 'none';
                            });
                            window.addEventListener('click', function(event) {
                                if (event.target == editModal) {
                                    editModal.style.display = 'none';
                                }
                            });
                        </script>
                    </div>
                    <?php
                    break;
                case 'logs':
                    // アクセスログセクション
                    ?>
                    <div class="section">
                        <h2>アクセスログ</h2>
                        <table>
                            <tr>
                                <th>ユーザー</th>
                                <th>アクション</th>
                                <th>詳細</th>
                                <th>タイムスタンプ</th>
                                <th>IPアドレス</th>
                            </tr>
                            <?php foreach (array_reverse($logs) as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['user'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($log['detail'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($log['timestamp'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($log['ip'], ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    <?php
                    break;
                case 'dashboard':
                default:
                    // ダッシュボードセクション
                    ?>
                    <div class="section">
                        <h2>ダッシュボード</h2>
                        <p>サムネイル付きリンク生成サービスの管理者画面へようこそ。</p>
                    </div>
                    <?php
                    break;
            }

            // ログアウト処理
            if ($action === 'logout') {
                session_destroy();
                header('Location: index.php');
                exit();
            }
            ?>

        <?php endif; ?>
    </div>
</body>
</html>
