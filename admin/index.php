<?php
session_start();

// セキュリティ対策: セッションハイジャック防止
ini_set('session.cookie_httponly', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

// パスの定義
define('USERS_FILE', __DIR__ . '/../users.json');
define('LINKS_FILE', __DIR__ . '/../links.json');
define('UPLOADS_DIR', __DIR__ . '/../uploads/');
define('TEMPLATES_DIR', __DIR__ . '/../templates/');
define('LOGS_DIR', __DIR__ . '/../logs/');

// 初期化
$errors = [];
$success = false;
$action_message = '';

// ユーザー認証チェック
function is_logged_in() {
    return isset($_SESSION['user']);
}

function is_admin() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}

// 必要なフォルダとファイルの存在確認と自動作成
function initialize_environment() {
    $dirs = [UPLOADS_DIR, TEMPLATES_DIR, LOGS_DIR];
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
    }
    $files = [USERS_FILE, LINKS_FILE];
    foreach ($files as $file) {
        if (!file_exists($file)) {
            file_put_contents($file, json_encode([]));
        }
    }
}

initialize_environment();

// ログ記録関数
function log_action($action, $details = '') {
    $logFile = LOGS_DIR . 'access.log';
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] Action: $action, Details: $details\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
}

// ユーザー情報の取得
function get_users() {
    return json_decode(file_get_contents(USERS_FILE), true);
}

// ユーザー情報の保存
function save_users($users) {
    file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
}

// リンク情報の取得
function get_links() {
    return json_decode(file_get_contents(LINKS_FILE), true);
}

// リンク情報の保存
function save_links($links) {
    file_put_contents(LINKS_FILE, json_encode($links, JSON_PRETTY_PRINT));
}

// パスワードのハッシュ化
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// パスワードの検証
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// CSRFトークン生成
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

// ユーザー認証
if (!is_logged_in() || !is_admin()) {
    header('Location: ../index.php');
    exit;
}

// ログアウト処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'logout') {
    log_action('Admin Logged Out', "ID: " . $_SESSION['user']['id']);
    session_unset();
    session_destroy();
    header('Location: ../index.php');
    exit;
}

// ユーザー管理機能
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRFトークンチェック
    if (!isset($_POST['token']) || !hash_equals($_SESSION['token'], $_POST['token'])) {
        $errors[] = '不正なリクエストです。';
    } else {
        $action = $_POST['action'];
        $users = get_users();

        if ($action === 'create_user') {
            $new_id = trim($_POST['new_id']);
            $new_password = $_POST['new_password'];
            $new_role = $_POST['new_role'];

            if (empty($new_id) || empty($new_password) || empty($new_role)) {
                $errors[] = '全ての項目を入力してください。';
            } else {
                // ユーザーIDの重複チェック
                foreach ($users as $user) {
                    if ($user['id'] === $new_id) {
                        $errors[] = '既に存在するユーザーIDです。';
                        break;
                    }
                }

                if (empty($errors)) {
                    $users[] = [
                        'id' => $new_id,
                        'password' => hash_password($new_password),
                        'role' => $new_role
                    ];
                    save_users($users);
                    $success = true;
                    $action_message = "ユーザー '$new_id' を作成しました。";
                    log_action('User Created', "ID: $new_id, Role: $new_role");
                }
            }
        }

        if ($action === 'delete_user') {
            $delete_id = $_POST['delete_id'];
            foreach ($users as $index => $user) {
                if ($user['id'] === $delete_id) {
                    if ($user['id'] === 'admin') {
                        $errors[] = '初期管理者は削除できません。';
                        break;
                    }
                    array_splice($users, $index, 1);
                    save_users($users);
                    $success = true;
                    $action_message = "ユーザー '$delete_id' を削除しました。";
                    log_action('User Deleted', "ID: $delete_id");
                    break;
                }
            }
            if (!isset($action_message)) {
                $errors[] = 'ユーザーが見つかりません。';
            }
        }

        if ($action === 'reset_password') {
            $reset_id = $_POST['reset_id'];
            $new_password = $_POST['new_password'];

            foreach ($users as &$user) {
                if ($user['id'] === $reset_id) {
                    $user['password'] = hash_password($new_password);
                    save_users($users);
                    $success = true;
                    $action_message = "ユーザー '$reset_id' のパスワードをリセットしました。";
                    log_action('Password Reset', "ID: $reset_id");
                    break;
                }
            }
            unset($user);
            if (!isset($action_message)) {
                $errors[] = 'ユーザーが見つかりません。';
            }
        }
    }
}

// バックアップ機能
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'backup') {
    // CSRFトークンチェック
    if (!isset($_POST['token']) || !hash_equals($_SESSION['token'], $_POST['token'])) {
        $errors[] = '不正なリクエストです。';
    } else {
        // バックアップファイル名
        $backup_name = 'backup_' . date('Ymd_His') . '.zip';
        $backup_path = LOGS_DIR . $backup_name;

        // ZIPアーカイブ作成
        $zip = new ZipArchive();
        if ($zip->open($backup_path, ZipArchive::CREATE) === TRUE) {
            // ファイルの追加
            $zip->addFile(USERS_FILE, 'users.json');
            $zip->addFile(LINKS_FILE, 'links.json');
            // uploads フォルダ
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(UPLOADS_DIR),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = 'uploads/' . substr($filePath, strlen(__DIR__ . '/../uploads/'));
                    $zip->addFile($filePath, $relativePath);
                }
            }
            // templates フォルダ
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(TEMPLATES_DIR),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = 'templates/' . substr($filePath, strlen(__DIR__ . '/../templates/'));
                    $zip->addFile($filePath, $relativePath);
                }
            }
            $zip->close();
            $success = true;
            $action_message = "バックアップが作成されました。";
            log_action('Backup Created', "Backup: $backup_name");
        } else {
            $errors[] = 'バックアップの作成に失敗しました。';
        }
    }
}

// ログ閲覧機能
$logs = [];
$log_file = LOGS_DIR . 'access.log';
if (file_exists($log_file)) {
    $logs = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
}

// HTML出力
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
            background-color: #121212;
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
        h1, h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
            font-size: 16px;
        }
        input[type="text"],
        input[type="url"],
        input[type="password"],
        textarea,
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
        input[type="url"]:focus,
        input[type="password"]:focus,
        textarea:focus,
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
            animation: shake 0.5s;
            margin-top: 10px;
        }
        @keyframes shake {
            0% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            50% { transform: translateX(5px); }
            75% { transform: translateX(-5px); }
            100% { transform: translateX(0); }
        }
        .success-message {
            background-color: #1e1e1e;
            color: #ffffff;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            animation: fadeInUp 0.5s;
        }
        .success-message p {
            margin-bottom: 10px;
        }
        .user-list, .log-list {
            margin-top: 30px;
        }
        .user-item, .log-item {
            background-color: #1e1e1e;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            animation: fadeInUp 0.3s;
        }
        .user-actions, .log-actions {
            margin-top: 10px;
        }
        .user-actions form {
            display: inline;
        }
        .log-actions form {
            display: inline;
        }
        /* バックアップボタン */
        .backup-button {
            background-color: #ff9800;
        }
        .backup-button:hover {
            background: linear-gradient(to right, #ffb74d, #ffa726);
            transform: scale(1.02);
        }
        /* メディアクエリ */
        @media screen and (max-width: 600px) {
            .container {
                padding: 10px;
            }
            button {
                padding: 10px;
            }
        }
    </style>
    <script>
        // JavaScriptをここに記述
        document.addEventListener('DOMContentLoaded', function() {
            // ユーザー編集ボタンの処理
            const editButtons = document.querySelectorAll('.edit-button');
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.dataset.userId;
                    const newPassword = prompt('新しいパスワードを入力してください:');
                    if (newPassword) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.style.display = 'none';

                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'action';
                        actionInput.value = 'reset_password';
                        form.appendChild(actionInput);

                        const tokenInput = document.createElement('input');
                        tokenInput.type = 'hidden';
                        tokenInput.name = 'token';
                        tokenInput.value = '<?php echo $_SESSION['token']; ?>';
                        form.appendChild(tokenInput);

                        const resetIdInput = document.createElement('input');
                        resetIdInput.type = 'hidden';
                        resetIdInput.name = 'reset_id';
                        resetIdInput.value = userId;
                        form.appendChild(resetIdInput);

                        const newPasswordInput = document.createElement('input');
                        newPasswordInput.type = 'hidden';
                        newPasswordInput.name = 'new_password';
                        newPasswordInput.value = newPassword;
                        form.appendChild(newPasswordInput);

                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });

            // ユーザー削除ボタンの確認
            const deleteButtons = document.querySelectorAll('.delete-button');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    if (!confirm('本当にこのユーザーを削除しますか？')) {
                        event.preventDefault();
                    }
                });
            });

            // バックアップボタンの確認
            const backupForm = document.getElementById('backupForm');
            if (backupForm) {
                backupForm.addEventListener('submit', function(event) {
                    if (!confirm('本当にバックアップを作成しますか？')) {
                        event.preventDefault();
                    }
                });
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>管理者画面</h1>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message">
                <p><?php echo $action_message; ?></p>
            </div>
        <?php endif; ?>

        <!-- ユーザー作成フォーム -->
        <h2>ユーザー管理</h2>
        <form method="POST">
            <input type="hidden" name="action" value="create_user">
            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
            <label>新規ユーザーID</label>
            <input type="text" name="new_id" required>

            <label>新規ユーザーパスワード</label>
            <input type="password" name="new_password" required>

            <label>役割</label>
            <select name="new_role" required>
                <option value="">選択してください</option>
                <option value="user">一般ユーザー</option>
                <option value="admin">管理者</option>
            </select>

            <button type="submit">ユーザーを作成</button>
        </form>

        <!-- ユーザー一覧 -->
        <div class="user-list">
            <h2>登録ユーザー一覧</h2>
            <?php
            $all_users = get_users();
            foreach ($all_users as $user):
                if ($user['id'] === 'admin') continue; // 初期管理者は表示しない
            ?>
                <div class="user-item">
                    <p><strong>ID:</strong> <?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>役割:</strong> <?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="user-actions">
                        <button type="button" class="edit-button" data-user-id="<?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?>">パスワードリセット</button>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                            <input type="hidden" name="delete_id" value="<?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="delete-button" style="background-color: #ff5252;">削除</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- バックアップ機能 -->
        <div class="backup-section">
            <h2>バックアップ</h2>
            <form method="POST" id="backupForm">
                <input type="hidden" name="action" value="backup">
                <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                <button type="submit" class="backup-button">バックアップを作成</button>
            </form>
        </div>

        <!-- ログ閲覧 -->
        <div class="log-list">
            <h2>アクセスログ</h2>
            <?php if (count($logs) === 0): ?>
                <p>ログはありません。</p>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <div class="log-item">
                        <p><?php echo htmlspecialchars($log, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</body>
</html>
