<?php
// セッションの開始
session_start();

// クエリパラメータのチェック
if (!isset($_GET['id']) || $_GET['id'] !== '123') {
    // 画面を真っ白にして何も表示しない
    exit;
}

// CSRFトークンの生成
if (empty($_SESSION['admin_token'])) {
    $_SESSION['admin_token'] = bin2hex(random_bytes(32));
}

// エラーメッセージと成功メッセージの初期化
$errors = [];
$success = false;

// 管理者ログアウト処理
if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_unset();
    session_destroy();
    setcookie("loggedin_admin", "", time() - 3600, "/");
    header("Location: ../index.php?id=123");
    exit;
}

// 管理者ログイン処理
if (!isset($_SESSION['admin_loggedin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_login') {
        if (!hash_equals($_SESSION['admin_token'], $_POST['token'])) {
            $errors[] = '不正なリクエストです。';
        } else {
            $admin_username = trim($_POST['admin_username']);
            $admin_password = trim($_POST['admin_password']);

            if ($admin_username === 'admin' && $admin_password === 'admin') {
                $_SESSION['admin_loggedin'] = true;
                setcookie("loggedin_admin", true, time() + (86400 * 30), "/"); // 30日間有効
                header("Location: admin/index.php?id=123");
                exit;
            } else {
                $errors[] = '管理者IDまたはパスワードが間違っています。';
            }
        }
    }

    // 管理者ログインフォームを表示
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <title>管理者ログイン - サムネイル付きリンク生成サービス</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            /* スタイルをここに記述 */
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
                animation: fadeIn 1s ease-in-out;
            }
            .login-container {
                background-color: #1e1e1e;
                padding: 30px;
                border-radius: 10px;
                width: 90%;
                max-width: 400px;
                box-shadow: 0 0 10px rgba(0,0,0,0.5);
                animation: scaleUp 0.5s ease-in-out;
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
            /* アニメーション */
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
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
        </style>
    </head>
    <body>
        <div class="login-container">
            <h1>管理者ログイン</h1>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo $_SESSION['admin_token']; ?>">
                <input type="hidden" name="action" value="admin_login">
                <label>管理者ID</label>
                <input type="text" name="admin_username" required>
                <label>パスワード</label>
                <input type="password" name="admin_password" required>
                <button type="submit">ログイン</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 管理者がログインしている場合の処理
if (isset($_SESSION['admin_loggedin']) && $_SESSION['admin_loggedin'] === true) {
    // ユーザー管理機能とリンク管理機能を実装

    // 新規ユーザー作成処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
        if (!hash_equals($_SESSION['admin_token'], $_POST['token'])) {
            $errors[] = '不正なリクエストです。';
        } else {
            $new_user_id = trim($_POST['new_user_id']);
            $new_user_password = trim($_POST['new_user_password']);

            if (empty($new_user_id) || empty($new_user_password)) {
                $errors[] = 'ユーザーIDとパスワードを入力してください。';
            } else {
                // users.jsonの読み込み
                if (!file_exists('../users.json')) {
                    $users = [];
                } else {
                    $users = json_decode(file_get_contents('../users.json'), true);
                }
                // ユーザーIDの重複チェック
                $duplicate = false;
                foreach ($users as $user) {
                    if ($user['id'] === $new_user_id) {
                        $duplicate = true;
                        break;
                    }
                }
                if ($duplicate) {
                    $errors[] = '既に存在するユーザーIDです。';
                } else {
                    // 新規ユーザーの追加
                    $users[] = [
                        'id' => $new_user_id,
                        'password' => $new_user_password,
                        'first_login' => true
                    ];
                    // users.jsonの書き込み
                    file_put_contents('../users.json', json_encode($users, JSON_PRETTY_PRINT));
                    $success = true;
                }
            }
        }
    }

    // ユーザーのリンク数の取得
    if (!file_exists('../users.json')) {
        $users = [];
    } else {
        $users = json_decode(file_get_contents('../users.json'), true);
    }
    if (!file_exists('../seisei.json')) {
        $links = [];
    } else {
        $links = json_decode(file_get_contents('../seisei.json'), true);
    }
    $user_link_counts = [];
    foreach ($users as $user) {
        $count = 0;
        foreach ($links as $link) {
            if ($link['user_id'] === $user['id']) {
                $count++;
            }
        }
        $user_link_counts[$user['id']] = $count;
    }

    // ユーザーのリンク詳細表示処理
    if (isset($_GET['view_user'])) {
        $view_user = $_GET['view_user'];
        // seisei.jsonの読み込み
        $links = json_decode(file_get_contents('../seisei.json'), true);
        $view_user_links = array_filter($links, function($link) use ($view_user) {
            return $link['user_id'] === $view_user;
        });
    }

    // 管理者ダッシュボードの表示
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <title>管理者ダッシュボード - サムネイル付きリンク生成サービス</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            /* スタイルをここに記述 */
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
            h1 {
                text-align: center;
                margin-bottom: 20px;
            }
            h2 {
                margin-top: 30px;
                margin-bottom: 10px;
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
            .success-message {
                background-color: #1e1e1e;
                color: #ffffff;
                border-radius: 10px;
                padding: 15px;
                margin-top: 20px;
                animation: fadeInUp 0.5s;
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
            /* テーブルスタイル */
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            th, td {
                padding: 10px;
                text-align: left;
                border-bottom: 1px solid #333;
            }
            th {
                background-color: #2a2a2a;
            }
            tr:hover {
                background-color: #333;
            }
            /* 編集フォーム */
            .edit-form {
                margin-top: 20px;
                padding: 15px;
                background-color: #1e1e1e;
                border-radius: 10px;
                animation: fadeIn 0.5s;
            }
            /* ログアウトボタン */
            .logout-button {
                background: #ff5252;
                color: #ffffff;
            }
            .logout-button:hover {
                background: #ff1744;
                transform: scale(1.02);
            }
            /* リンク一覧テーブル */
            .user-links-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            .user-links-table th, .user-links-table td {
                padding: 10px;
                text-align: left;
                border-bottom: 1px solid #333;
            }
            .user-links-table th {
                background-color: #2a2a2a;
            }
            .user-links-table tr:hover {
                background-color: #333;
            }
            /* 編集ボタン */
            .edit-button {
                background: #00e5ff;
                color: #000;
                border: none;
                border-radius: 5px;
                padding: 5px 10px;
                cursor: pointer;
                transition: transform 0.2s, background 0.2s;
            }
            .edit-button:hover {
                background: #00b0ff;
                transform: scale(1.05);
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>管理者ダッシュボード</h1>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success-message">
                    <p>ユーザーが正常に作成されました。</p>
                </div>
            <?php endif; ?>

            <!-- 管理者ログアウトボタン -->
            <form method="POST" style="text-align: right;">
                <input type="hidden" name="action" value="logout">
                <input type="hidden" name="token" value="<?php echo $_SESSION['admin_token']; ?>">
                <button type="submit" class="logout-button">ログアウト</button>
            </form>

            <!-- 新規ユーザー作成フォーム -->
            <h2>新規ユーザー作成</h2>
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo $_SESSION['admin_token']; ?>">
                <input type="hidden" name="action" value="create_user">
                <label>ユーザーID</label>
                <input type="text" name="new_user_id" required>
                <label>パスワード</label>
                <input type="password" name="new_user_password" required>
                <button type="submit">ユーザーを作成</button>
            </form>

            <!-- ユーザー一覧 -->
            <h2>ユーザー一覧</h2>
            <?php if (count($users) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ユーザーID</th>
                            <th>生成リンク数</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo isset($user_link_counts[$user['id']]) ? $user_link_counts[$user['id']] : 0; ?></td>
                                <td><a href="index.php?id=123&view_user=<?php echo urlencode($user['id']); ?>" class="edit-button">詳細を見る</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>ユーザーが存在しません。</p>
            <?php endif; ?>

            <!-- ユーザーのリンク詳細表示 -->
            <?php if (isset($view_user)): ?>
                <h2><?php echo htmlspecialchars($view_user, ENT_QUOTES, 'UTF-8'); ?> の生成したリンク一覧</h2>
                <?php if (count($view_user_links) > 0): ?>
                    <table class="user-links-table">
                        <thead>
                            <tr>
                                <th>タイトル</th>
                                <th>リンク</th>
                                <th>作成日時</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($view_user_links as $link): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($link['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><a href="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/' . $link['unique_id']; ?>" target="_blank"><?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/' . $link['unique_id']; ?></a></td>
                                    <td><?php echo $link['created_at']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>このユーザーはまだリンクを生成していません。</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
