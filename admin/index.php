<?php
session_start();

// データファイルのパス
define('USERS_FILE', __DIR__ . '/../data/users.json');
define('SEISEI_FILE', __DIR__ . '/../data/seisei.json');
define('LOGS_FILE', __DIR__ . '/../data/logs.json');
define('BACKUP_DIR', __DIR__ . '/../backups/');

// 管理者の固定IDとパスワード
define('ADMIN_ID', 'admin');
define('ADMIN_PASSWORD', 'admin');

// ハッシュアルゴリズム
define('HASH_ALGO', PASSWORD_BCRYPT);

// 関数定義

/**
 * ログを記録する関数
 */
function log_event($user_id, $event) {
    $log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => $user_id,
        'event' => $event
    ];
    $logs = json_decode(file_get_contents(LOGS_FILE), true);
    $logs[] = $log;
    file_put_contents(LOGS_FILE, json_encode($logs, JSON_PRETTY_PRINT));
}

/**
 * ユーザー情報を取得する関数
 */
function get_users() {
    if (!file_exists(USERS_FILE)) {
        file_put_contents(USERS_FILE, json_encode([], JSON_PRETTY_PRINT));
    }
    return json_decode(file_get_contents(USERS_FILE), true);
}

/**
 * ユーザー情報を保存する関数
 */
function save_users($users) {
    file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
}

/**
 * リンク情報を取得する関数
 */
function get_links() {
    if (!file_exists(SEISEI_FILE)) {
        file_put_contents(SEISEI_FILE, json_encode([], JSON_PRETTY_PRINT));
    }
    return json_decode(file_get_contents(SEISEI_FILE), true);
}

/**
 * リンク情報を保存する関数
 */
function save_links($links) {
    file_put_contents(SEISEI_FILE, json_encode($links, JSON_PRETTY_PRINT));
}

/**
 * バックアップを作成する関数
 */
function create_backup() {
    if (!is_dir(BACKUP_DIR)) {
        mkdir(BACKUP_DIR, 0777, true);
    }
    $timestamp = date('Ymd_His');
    copy(USERS_FILE, BACKUP_DIR . "users_backup_$timestamp.json");
    copy(SEISEI_FILE, BACKUP_DIR . "seisei_backup_$timestamp.json");
    copy(LOGS_FILE, BACKUP_DIR . "logs_backup_$timestamp.json");
}

// セキュリティ対策: CSRFトークン生成
if (empty($_SESSION['csrf_token_admin'])) {
    $_SESSION['csrf_token_admin'] = bin2hex(random_bytes(32));
}

// セキュリティ対策: ドメインへのアクセス制限
if (!isset($_GET['id']) || $_GET['id'] !== '123') {
    // 画面を真っ白にする
    exit;
}

// ログイン処理
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRFトークンチェック
    if (!isset($_POST['csrf_token_admin']) || $_POST['csrf_token_admin'] !== $_SESSION['csrf_token_admin']) {
        $errors[] = '不正なリクエストです。';
    } else {
        // 管理者ログインフォームの処理
        if (isset($_POST['admin_login'])) {
            $admin_username = htmlspecialchars(trim($_POST['admin_username']));
            $admin_password = trim($_POST['admin_password']);

            if ($admin_username === ADMIN_ID && $admin_password === ADMIN_PASSWORD) {
                // 管理者としてログイン
                $_SESSION['admin_logged_in'] = true;
                // クッキーに保存（30日）
                setcookie('admin_auth', 'admin', time() + (86400 * 30), "/", "", true, true);
                log_event('admin', 'Admin logged in');
                header('Location: index.php');
                exit;
            } else {
                $errors[] = 'IDまたはパスワードが正しくありません。';
                log_event($admin_username, 'Failed admin login attempt');
            }
        }

        // ユーザー作成処理
        if (isset($_POST['create_user'])) {
            $new_user_id = htmlspecialchars(trim($_POST['new_user_id']));
            $new_user_password = trim($_POST['new_user_password']);

            if (empty($new_user_id) || empty($new_user_password)) {
                $errors[] = 'ユーザーIDとパスワードを入力してください。';
            } elseif (strlen($new_user_password) < 6) {
                $errors[] = 'パスワードは6文字以上で設定してください。';
            } else {
                $users = get_users();
                $user_exists = false;
                foreach ($users as $user) {
                    if ($user['id'] === $new_user_id) {
                        $user_exists = true;
                        break;
                    }
                }
                if ($user_exists) {
                    $errors[] = '既に存在するユーザーIDです。';
                } else {
                    $hashed_password = password_hash($new_user_password, HASH_ALGO);
                    $new_user = [
                        'id' => $new_user_id,
                        'password' => $hashed_password,
                        'first_login' => true
                    ];
                    $users[] = $new_user;
                    save_users($users);
                    $success = true;
                    log_event('admin', "User created: $new_user_id");
                }
            }
        }

        // ユーザー削除処理
        if (isset($_POST['delete_user'])) {
            $delete_user_id = htmlspecialchars(trim($_POST['delete_user_id']));
            if ($delete_user_id === ADMIN_ID) {
                $errors[] = '管理者ユーザーは削除できません。';
            } else {
                $users = get_users();
                $updated_users = [];
                $found = false;
                foreach ($users as $user) {
                    if ($user['id'] !== $delete_user_id) {
                        $updated_users[] = $user;
                    } else {
                        $found = true;
                        log_event('admin', "User deleted: $delete_user_id");
                    }
                }
                if ($found) {
                    save_users($updated_users);
                    $success = true;
                } else {
                    $errors[] = '指定されたユーザーが存在しません。';
                }
            }
        }

        // ユーザー編集処理
        if (isset($_POST['edit_user'])) {
            $edit_user_id = htmlspecialchars(trim($_POST['edit_user_id']));
            $edit_user_password = trim($_POST['edit_user_password']);

            if (empty($edit_user_id) || empty($edit_user_password)) {
                $errors[] = 'ユーザーIDと新しいパスワードを入力してください。';
            } elseif (strlen($edit_user_password) < 6) {
                $errors[] = 'パスワードは6文字以上で設定してください。';
            } else {
                $users = get_users();
                $user_found = false;
                foreach ($users as &$user) {
                    if ($user['id'] === $edit_user_id) {
                        $user['password'] = password_hash($edit_user_password, HASH_ALGO);
                        $user['first_login'] = true; // パスワード変更後は初回ログインフラグを有効に
                        $user_found = true;
                        log_event('admin', "User edited: $edit_user_id");
                        break;
                    }
                }
                if ($user_found) {
                    save_users($users);
                    $success = true;
                } else {
                    $errors[] = '指定されたユーザーが存在しません。';
                }
            }
        }
    }
}

// 管理者がログインしているか確認
$admin_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
if (!$admin_logged_in) {
    // クッキーから確認
    if (isset($_COOKIE['admin_auth']) && $_COOKIE['admin_auth'] === 'admin') {
        $_SESSION['admin_logged_in'] = true;
        log_event('admin', 'Admin logged in via cookie');
        header('Location: index.php');
        exit;
    }
}

if (!$admin_logged_in && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // 管理者ログインフォームを表示
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <title>管理者ログイン - サムネイル付きリンク生成サービス</title>
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
            .login-container {
                background-color: #1e1e1e;
                padding: 30px;
                border-radius: 10px;
                width: 90%;
                max-width: 400px;
                animation: fadeInUp 0.5s;
            }
            h2 {
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
            input[type="text"]:focus,
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
                animation: shake 0.5s;
                margin-top: 10px;
                text-align: center;
            }
            @keyframes shake {
                0% { transform: translateX(0); }
                25% { transform: translateX(-5px); }
                50% { transform: translateX(5px); }
                75% { transform: translateX(-5px); }
                100% { transform: translateX(0); }
            }
            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2>管理者ログイン</h2>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token_admin" value="<?php echo $_SESSION['csrf_token_admin']; ?>">
                <label>ユーザーID</label>
                <input type="text" name="admin_username" required>

                <label>パスワード</label>
                <input type="password" name="admin_password" required>

                <button type="submit" name="admin_login">ログイン</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 管理者がログインしている場合、管理者ダッシュボードを表示
if ($admin_logged_in) {
    // ユーザーのリンク一覧の取得
    $users = get_users();
    $links = get_links();
    $logs = json_decode(file_get_contents(LOGS_FILE), true);
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
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
                animation: fadeIn 1s ease-in-out;
            }
            h1 {
                text-align: center;
                margin-bottom: 20px;
            }
            h2 {
                margin-top: 40px;
                margin-bottom: 20px;
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
                animation: shake 0.5s;
                margin-top: 10px;
            }
            .success {
                color: #4caf50;
                font-size: 14px;
                margin-top: 10px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
            }
            th, td {
                border: 1px solid #ffffff;
                padding: 8px;
                text-align: left;
            }
            th {
                background-color: #2a2a2a;
            }
            tr:nth-child(even) {
                background-color: #1e1e1e;
            }
            .logout-link {
                color: #00e5ff;
                text-decoration: none;
                float: right;
            }
            /* モーダルスタイル */
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow-y: auto;
                background-color: rgba(0,0,0,0.8);
                animation: fadeIn 0.5s;
            }
            .modal-content {
                background-color: #1e1e1e;
                margin: 50px auto;
                padding: 20px;
                border-radius: 10px;
                width: 90%;
                max-width: 500px;
                animation: scaleUp 0.3s ease-in-out;
            }
            .close {
                color: #ffffff;
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }
            /* アニメーション */
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes scaleUp {
                from { transform: scale(0.8); }
                to { transform: scale(1); }
            }
        </style>
        <script>
            // JavaScriptをここに記述
            document.addEventListener('DOMContentLoaded', function() {
                // ユーザー編集モーダルの処理
                const editModal = document.getElementById('editModal');
                const editClose = document.getElementById('editClose');
                const editForm = document.getElementById('editForm');

                function openEditModal(userId) {
                    editModal.style.display = 'block';
                    document.getElementById('edit_user_id').value = userId;
                }

                editClose.addEventListener('click', function() {
                    editModal.style.display = 'none';
                });
                window.addEventListener('click', function(event) {
                    if (event.target == editModal) {
                        editModal.style.display = 'none';
                    }
                });

                // ユーザー削除の確認
                function confirmDelete(userId) {
                    if (confirm('本当にこのユーザーを削除しますか？')) {
                        document.getElementById('delete_user_id').value = userId;
                        document.getElementById('deleteForm').submit();
                    }
                }
            });
        </script>
</head>
<body>
    <div class="container">
        <a href="../index.php?id=123&action=logout" class="logout-link">ログアウト</a>
        <h1>管理者画面</h1>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success">
                <p>操作が正常に完了しました。</p>
            </div>
        <?php endif; ?>

        <!-- ユーザー作成フォーム -->
        <h2>新規ユーザーの作成</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token_admin" value="<?php echo $_SESSION['csrf_token_admin']; ?>">
            <label>ユーザーID</label>
            <input type="text" name="new_user_id" required>

            <label>パスワード</label>
            <input type="password" name="new_user_password" required>

            <button type="submit" name="create_user">ユーザーを作成</button>
        </form>

        <!-- ユーザー一覧 -->
        <h2 style="margin-top:40px;">ユーザー一覧</h2>
        <table>
            <thead>
                <tr>
                    <th>ユーザーID</th>
                    <th>初回ログイン</th>
                    <th>アクション</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                        <td><?php echo isset($user['first_login']) && $user['first_login'] ? 'はい' : 'いいえ'; ?></td>
                        <td>
                            <button type="button" onclick="openEditModal('<?php echo htmlspecialchars($user['id']); ?>')">編集</button>
                            <?php if ($user['id'] !== ADMIN_ID): ?>
                                <button type="button" onclick="confirmDelete('<?php echo htmlspecialchars($user['id']); ?>')">削除</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- ユーザー編集モーダル -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close" id="editClose">&times;</span>
                <h2>ユーザーの編集</h2>
                <form method="POST" id="editForm">
                    <input type="hidden" name="csrf_token_admin" value="<?php echo $_SESSION['csrf_token_admin']; ?>">
                    <input type="hidden" id="edit_user_id" name="edit_user_id">

                    <label>新しいパスワード</label>
                    <input type="password" name="edit_user_password" required>

                    <button type="submit" name="edit_user">変更を保存</button>
                </form>
            </div>
        </div>

        <!-- ユーザー削除フォーム（非表示） -->
        <form method="POST" id="deleteForm" style="display:none;">
            <input type="hidden" name="csrf_token_admin" value="<?php echo $_SESSION['csrf_token_admin']; ?>">
            <input type="hidden" id="delete_user_id" name="delete_user_id">
            <button type="submit" name="delete_user">削除</button>
        </form>

        <!-- アクセスログ -->
        <h2 style="margin-top:40px;">アクセスログ</h2>
        <table>
            <thead>
                <tr>
                    <th>タイムスタンプ</th>
                    <th>ユーザーID</th>
                    <th>イベント</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_reverse($logs) as $log): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($log['timestamp']); ?></td>
                        <td><?php echo htmlspecialchars($log['user_id']); ?></td>
                        <td><?php echo htmlspecialchars($log['event']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
