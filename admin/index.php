<?php
// セッションの開始
session_start();

// 自動作成するディレクトリとファイルのパス
$baseDir = __DIR__ . '/../';
$dataDir = $baseDir . '/data';
$uploadsDir = $baseDir . '/uploads';
$tempDir = $baseDir . '/temp';
$usersFile = $dataDir . '/users.json';
$seiseiFile = $dataDir . '/seisei.json';
$logsFile = $dataDir . '/logs.json';

// 必要なディレクトリとファイルの存在確認と自動作成
function initializeDirectoriesAndFiles($dataDir, $uploadsDir, $tempDir, $usersFile, $seiseiFile, $logsFile) {
    if (!file_exists($dataDir)) {
        mkdir($dataDir, 0777, true);
    }

    if (!file_exists($uploadsDir)) {
        mkdir($uploadsDir, 0777, true);
    }

    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
    }

    if (!file_exists($usersFile)) {
        file_put_contents($usersFile, json_encode([
            "admin" => [
                "password" => password_hash("admin", PASSWORD_DEFAULT),
                "is_admin" => true,
                "force_password_change" => false
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    if (!file_exists($seiseiFile)) {
        file_put_contents($seiseiFile, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    if (!file_exists($logsFile)) {
        file_put_contents($logsFile, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

initializeDirectoriesAndFiles($dataDir, $uploadsDir, $tempDir, $usersFile, $seiseiFile, $logsFile);

// ユーザー情報の取得
$users = json_decode(file_get_contents($usersFile), true);

// エラーメッセージの初期化
$errors = [];
$success = false;
$successMessage = '';

// 管理者以外はアクセス不可
if (!isset($_SESSION['user']) || !$users[$_SESSION['user']]['is_admin']) {
    // 管理者ログインフォーム
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
        $admin_username = trim($_POST['admin_username']);
        $admin_password = $_POST['admin_password'];
        if ($admin_username === 'admin') {
            if (password_verify($admin_password, $users['admin']['password'])) {
                $_SESSION['user'] = 'admin';
                // ログインログの記録
                $logs = json_decode(file_get_contents($logsFile), true);
                $logs[] = [
                    "user" => "admin",
                    "action" => "admin_login",
                    "timestamp" => date("Y-m-d H:i:s")
                ];
                file_put_contents($logsFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                header("Location: index.php");
                exit();
            } else {
                $errors[] = "管理者パスワードが正しくありません。";
                // 不正ログイン試行の記録
                $logs = json_decode(file_get_contents($logsFile), true);
                $logs[] = [
                    "user" => "admin",
                    "action" => "failed_admin_login",
                    "timestamp" => date("Y-m-d H:i:s")
                ];
                file_put_contents($logsFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        } else {
            $errors[] = "管理者IDが正しくありません。";
            // 不正ログイン試行の記録
            $logs = json_decode(file_get_contents($logsFile), true);
            $logs[] = [
                "user" => "admin",
                "action" => "failed_admin_login",
                "timestamp" => date("Y-m-d H:i:s")
            ];
            file_put_contents($logsFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    // 管理者ログインフォームの表示
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <title>管理者ログイン</title>
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
                overflow: hidden;
            }
            .container {
                width: 90%;
                max-width: 400px;
                padding: 20px;
                background-color: #1e1e1e;
                border-radius: 10px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                animation: fadeInUp 0.5s;
            }
            h1 {
                text-align: center;
                margin-bottom: 20px;
                font-size: 24px;
            }
            label {
                display: block;
                margin-top: 15px;
                font-weight: bold;
                font-size: 16px;
            }
            input[type="text"],
            input[type="password"],
            textarea {
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
            textarea:focus {
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
                transition: transform 0.2s;
            }
            button:hover {
                background: linear-gradient(to right, #00b0ff, #00e5ff);
                transform: scale(1.02);
            }
            .error {
                color: #ff5252;
                font-size: 14px;
                margin-top: 10px;
                text-align: center;
                animation: shake 0.5s;
            }
            /* アニメーション */
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
            <h1>管理者ログイン</h1>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <label>管理者ID</label>
                <input type="text" name="admin_username" required>

                <label>パスワード</label>
                <input type="password" name="admin_password" required>

                <button type="submit" name="admin_login">ログイン</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// 管理者としてログインしている場合のみ以下のコードを実行
if (isset($_SESSION['user']) && $users[$_SESSION['user']]['is_admin']) {
    $currentAdmin = $_SESSION['user'];
    // ユーザー管理処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 新規ユーザー作成
        if (isset($_POST['create_user'])) {
            $newUser = trim($_POST['new_username']);
            $newPassword = $_POST['new_password'];
            if (empty($newUser) || empty($newPassword)) {
                $errors[] = "ユーザー名とパスワードを入力してください。";
            } elseif (isset($users[$newUser])) {
                $errors[] = "ユーザー名が既に存在します。";
            } else {
                // 新規ユーザーの追加
                $users[$newUser] = [
                    "password" => password_hash($newPassword, PASSWORD_DEFAULT),
                    "is_admin" => false,
                    "force_password_change" => true
                ];
                file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $successMessage = "ユーザーが作成されました。";
            }
        }

        // ユーザー削除
        if (isset($_POST['delete_user'])) {
            $deleteUser = $_POST['delete_user'];
            if ($deleteUser === 'admin') {
                $errors[] = "管理者アカウントは削除できません。";
            } elseif (isset($users[$deleteUser])) {
                unset($users[$deleteUser]);
                file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $successMessage = "ユーザーが削除されました。";
            } else {
                $errors[] = "指定されたユーザーが存在しません。";
            }
        }

        // ユーザー編集
        if (isset($_POST['edit_user'])) {
            $editUser = $_POST['edit_user'];
            $editPassword = $_POST['edit_password'];
            $editIsAdmin = isset($_POST['edit_is_admin']) ? true : false;
            if (isset($users[$editUser])) {
                if (!empty($editPassword)) {
                    $users[$editUser]['password'] = password_hash($editPassword, PASSWORD_DEFAULT);
                    $users[$editUser]['force_password_change'] = false;
                }
                $users[$editUser]['is_admin'] = $editIsAdmin;
                file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $successMessage = "ユーザー情報が更新されました。";
            } else {
                $errors[] = "指定されたユーザーが存在しません。";
            }
        }

        // パスワードリセット
        if (isset($_POST['reset_password'])) {
            $resetUser = $_POST['reset_password'];
            if (isset($users[$resetUser])) {
                $newPassword = bin2hex(random_bytes(4)); // ランダムな8文字のパスワード
                $users[$resetUser]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                $users[$resetUser]['force_password_change'] = true;
                file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $successMessage = "パスワードがリセットされました。新しいパスワード: " . htmlspecialchars($newPassword, ENT_QUOTES, 'UTF-8');
            } else {
                $errors[] = "指定されたユーザーが存在しません。";
            }
        }

        // バックアップ処理
        if (isset($_POST['backup'])) {
            $backupDir = $dataDir . '/backup';
            if (!file_exists($backupDir)) {
                mkdir($backupDir, 0777, true);
            }
            $timestamp = date("Ymd_His");
            if (file_exists($usersFile)) {
                copy($usersFile, $backupDir . "/users_backup_$timestamp.json");
            }
            if (file_exists($seiseiFile)) {
                copy($seiseiFile, $backupDir . "/seisei_backup_$timestamp.json");
            }
            if (file_exists($logsFile)) {
                copy($logsFile, $backupDir . "/logs_backup_$timestamp.json");
            }
            $successMessage = "データがバックアップされました。";
        }
    }

    // ユーザー一覧の取得
    $userList = $users;
    unset($userList['admin']); // 管理者を一覧から除外

    // アクセスログの取得
    $logs = json_decode(file_get_contents($logsFile), true);
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <title>管理者ダッシュボード</title>
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
                padding: 20px;
            }
            h1 {
                text-align: center;
                margin-bottom: 20px;
                font-size: 24px;
            }
            .error {
                color: #ff5252;
                font-size: 14px;
                margin-top: 10px;
                text-align: center;
                animation: shake 0.5s;
            }
            .success-message {
                background-color: #1e1e1e;
                color: #ffffff;
                border-radius: 10px;
                padding: 15px;
                margin-top: 20px;
                animation: fadeInUp 0.5s;
            }
            /* アニメーション */
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
            /* フォームスタイル */
            form {
                background-color: #1e1e1e;
                padding: 20px;
                border-radius: 10px;
                margin-bottom: 20px;
                animation: fadeIn 0.5s;
            }
            label {
                display: block;
                margin-top: 15px;
                font-weight: bold;
                font-size: 16px;
            }
            input[type="text"],
            input[type="password"],
            input[type="url"],
            textarea {
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
            input[type="url"]:focus,
            textarea:focus {
                background-color: #3a3a3a;
                outline: none;
            }
            button {
                background: linear-gradient(to right, #00e5ff, #00b0ff);
                color: #000;
                border: none;
                border-radius: 5px;
                font-size: 16px;
                padding: 10px;
                margin-top: 20px;
                cursor: pointer;
                width: 100%;
                transition: transform 0.2s;
            }
            button:hover {
                background: linear-gradient(to right, #00b0ff, #00e5ff);
                transform: scale(1.02);
            }
            /* テーブルスタイル */
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            th, td {
                padding: 10px;
                border: 1px solid #2a2a2a;
                text-align: left;
            }
            th {
                background-color: #2a2a2a;
            }
            tr:hover {
                background-color: #3a3a3a;
            }
            /* アクションボタン */
            .action-buttons button {
                width: auto;
                margin-right: 5px;
                padding: 5px 10px;
                font-size: 14px;
                cursor: pointer;
                border: none;
                border-radius: 3px;
            }
            .action-buttons button.edit {
                background-color: #2196f3;
                color: #fff;
            }
            .action-buttons button.delete {
                background-color: #f44336;
                color: #fff;
            }
            .action-buttons button.reset {
                background-color: #ff9800;
                color: #fff;
            }
            /* ログ表示 */
            .logs {
                margin-top: 20px;
            }
            .logs table {
                width: 100%;
                border-collapse: collapse;
            }
            .logs th, .logs td {
                padding: 5px;
                border: 1px solid #2a2a2a;
                font-size: 12px;
            }
            .logs th {
                background-color: #2a2a2a;
            }
            /* バックアップボタン */
            .backup-button {
                background: #ff9800;
                color: #000;
            }
            .backup-button:hover {
                background: #ffc107;
            }
            /* 管理者ログアウトボタン */
            .logout-button {
                background: #ff5252;
                color: #fff;
                margin-top: 20px;
            }
            .logout-button:hover {
                background: #ff8a80;
            }
            /* アニメーション */
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
        </style>
        <script>
            // JavaScriptをここに記述
            document.addEventListener('DOMContentLoaded', function() {
                // ユーザー編集モーダルの処理（必要に応じて）
            });
        </script>
    </head>
    <body>
        <h1>管理者ダッシュボード</h1>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($successMessage)): ?>
            <div class="success-message">
                <p><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>

        <!-- 新規ユーザー作成フォーム -->
        <form method="POST">
            <h2>新規ユーザーの作成</h2>
            <label>ユーザー名</label>
            <input type="text" name="new_username" required>

            <label>パスワード</label>
            <input type="password" name="new_password" required>

            <button type="submit" name="create_user">ユーザーを作成</button>
        </form>

        <!-- ユーザー一覧 -->
        <h2>ユーザー一覧</h2>
        <table>
            <tr>
                <th>ユーザー名</th>
                <th>管理者</th>
                <th>操作</th>
            </tr>
            <?php foreach ($userList as $username => $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo $user['is_admin'] ? 'はい' : 'いいえ'; ?></td>
                    <td class="action-buttons">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="edit_user" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="edit">編集</button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="delete_user" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="delete">削除</button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="reset_password" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="reset">パスワードリセット</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <!-- アクセスログ -->
        <div class="logs">
            <h2>アクセスログ</h2>
            <table>
                <tr>
                    <th>ユーザー名</th>
                    <th>アクション</th>
                    <th>タイムスタンプ</th>
                </tr>
                <?php if (!empty($logs)): ?>
                    <?php foreach (array_reverse($logs) as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['user'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($log['timestamp'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">ログがありません。</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- バックアップボタン -->
        <form method="POST">
            <button type="submit" name="backup" class="backup-button">データのバックアップ</button>
        </form>

        <!-- 管理者ログアウトボタン -->
        <form method="GET" style="margin-top: 20px;">
            <button type="submit" name="action" value="logout" class="logout-button">ログアウト</button>
        </form>
    </body>
    </html>
    ```
    
### `change_password.php`

ユーザーが初回ログイン時にパスワードを変更するためのページです。`wwwroot/change_password.php` として作成してください。

```php
<?php
// セッションの開始
session_start();

// 自動作成するディレクトリとファイルのパス
$baseDir = __DIR__;
$dataDir = $baseDir . '/data';
$usersFile = $dataDir . '/users.json';
$logsFile = $dataDir . '/logs.json';

// 必要なディレクトリとファイルの存在確認と自動作成
if (!file_exists($usersFile)) {
    file_put_contents($usersFile, json_encode([
        "admin" => [
            "password" => password_hash("admin", PASSWORD_DEFAULT),
            "is_admin" => true,
            "force_password_change" => false
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

if (!file_exists($logsFile)) {
    file_put_contents($logsFile, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ユーザー情報の取得
$users = json_decode(file_get_contents($usersFile), true);

// エラーメッセージの初期化
$errors = [];
$success = false;

// ログインしていない場合はログインページにリダイレクト
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$currentUser = $_SESSION['user'];

// 管理者以外で強制パスワード変更が必要な場合
if (!$users[$currentUser]['is_admin'] && $users[$currentUser]['force_password_change'] === false) {
    header("Location: index.php");
    exit();
}

// パスワード変更処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if (empty($newPassword) || empty($confirmPassword)) {
        $errors[] = "新しいパスワードと確認パスワードを入力してください。";
    } elseif ($newPassword !== $confirmPassword) {
        $errors[] = "新しいパスワードと確認パスワードが一致しません。";
    } elseif (strlen($newPassword) < 6) {
        $errors[] = "パスワードは最低6文字必要です。";
    } else {
        // パスワードの更新
        $users[$currentUser]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $users[$currentUser]['force_password_change'] = false;
        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $success = true;
        // ログインログの記録
        $logs = json_decode(file_get_contents($logsFile), true);
        $logs[] = [
            "user" => $currentUser,
            "action" => "password_changed",
            "timestamp" => date("Y-m-d H:i:s")
        ];
        file_put_contents($logsFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>パスワード変更</title>
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
            overflow: hidden;
        }
        .container {
            width: 90%;
            max-width: 400px;
            padding: 20px;
            background-color: #1e1e1e;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            animation: fadeInUp 0.5s;
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
            font-size: 24px;
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
            transition: transform 0.2s;
        }
        button:hover {
            background: linear-gradient(to right, #00b0ff, #00e5ff);
            transform: scale(1.02);
        }
        .error {
            color: #ff5252;
            font-size: 14px;
            margin-top: 10px;
            text-align: center;
            animation: shake 0.5s;
        }
        .success-message {
            background-color: #1e1e1e;
            color: #ffffff;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            animation: fadeInUp 0.5s;
        }
        /* アニメーション */
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
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message">
                <p>パスワードが正常に変更されました。</p>
                <a href="index.php" style="color: #00e5ff; text-decoration: none;">ログインページへ</a>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
            <form method="POST">
                <label>新しいパスワード</label>
                <input type="password" name="new_password" required>

                <label>パスワードの確認</label>
                <input type="password" name="confirm_password" required>

                <button type="submit">パスワードを変更</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
