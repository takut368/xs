<?php
session_start();

// データファイルのパス
define('USERS_FILE', __DIR__ . '/data/users.json');
define('SEISEI_FILE', __DIR__ . '/data/seisei.json');
define('LOGS_FILE', __DIR__ . '/data/logs.json');
define('BACKUP_DIR', __DIR__ . '/backups/');

// 管理者の固定IDとパスワード
define('ADMIN_ID', 'admin');
define('ADMIN_PASSWORD', 'admin');

// CORS対策（必要に応じて設定）
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

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
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// セキュリティ対策: ドメインへのアクセス制限
if (!isset($_GET['id']) || $_GET['id'] !== '123') {
    // 画面を真っ白にする
    exit;
}

// ハッシュアルゴリズム
define('HASH_ALGO', PASSWORD_BCRYPT);

// ログイン処理
$errors = [];
$success = false;
$generatedLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRFトークンチェック
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = '不正なリクエストです。';
    } else {
        // ログインフォームの処理
        if (isset($_POST['login'])) {
            $username = htmlspecialchars(trim($_POST['username']));
            $password = trim($_POST['password']);
    
            if ($username === ADMIN_ID && $password === ADMIN_PASSWORD) {
                // 管理者としてログイン
                $_SESSION['user'] = [
                    'id' => ADMIN_ID,
                    'is_admin' => true
                ];
                // クッキーに保存（30日）
                setcookie('auth', $_SESSION['user']['id'], time() + (86400 * 30), "/", "", true, true);
                log_event($username, 'Admin logged in');
                header('Location: admin/index.php');
                exit;
            } else {
                // 一般ユーザーとしてログイン
                $users = get_users();
                $user_found = false;
                foreach ($users as $user) {
                    if ($user['id'] === $username && password_verify($password, $user['password'])) {
                        $user_found = true;
                        $_SESSION['user'] = [
                            'id' => $username,
                            'is_admin' => false
                        ];
                        // クッキーに保存（30日）
                        setcookie('auth', $_SESSION['user']['id'], time() + (86400 * 30), "/", "", true, true);
                        log_event($username, 'User logged in');
                        break;
                    }
                }
                if (!$user_found) {
                    $errors[] = 'IDまたはパスワードが正しくありません。';
                    log_event($username, 'Failed login attempt');
                }
            }
        }
    
        // パスワード変更フォームの処理
        if (isset($_POST['change_password'])) {
            if (!isset($_SESSION['user'])) {
                $errors[] = 'ログインが必要です。';
            } else {
                $new_password = trim($_POST['new_password']);
                $confirm_password = trim($_POST['confirm_password']);
    
                if (empty($new_password) || empty($confirm_password)) {
                    $errors[] = 'パスワードを入力してください。';
                } elseif ($new_password !== $confirm_password) {
                    $errors[] = 'パスワードが一致しません。';
                } elseif (strlen($new_password) < 6) {
                    $errors[] = 'パスワードは6文字以上で設定してください。';
                } else {
                    $users = get_users();
                    foreach ($users as &$user) {
                        if ($user['id'] === $_SESSION['user']['id']) {
                            $user['password'] = password_hash($new_password, HASH_ALGO);
                            $user['first_login'] = false;
                            break;
                        }
                    }
                    save_users($users);
                    $success = true;
                    log_event($_SESSION['user']['id'], 'Password changed');
                }
            }
        }
    }
}

// セッションからユーザー情報を取得
if (!isset($_SESSION['user']) && isset($_COOKIE['auth'])) {
    $auth = htmlspecialchars($_COOKIE['auth']);
    if ($auth === ADMIN_ID) {
        $_SESSION['user'] = [
            'id' => ADMIN_ID,
            'is_admin' => true
        ];
    } else {
        $users = get_users();
        foreach ($users as $user) {
            if ($user['id'] === $auth) {
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'is_admin' => false
                ];
                break;
            }
        }
    }
}

// ログアウト処理
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (isset($_SESSION['user'])) {
        log_event($_SESSION['user']['id'], 'User logged out');
    }
    session_unset();
    session_destroy();
    setcookie('auth', '', time() - 3600, "/", "", true, true);
    header('Location: index.php?id=123');
    exit;
}

// ユーザーがログインしているか確認
$logged_in = isset($_SESSION['user']);
$is_admin = $logged_in && $_SESSION['user']['is_admin'];

// 管理者であれば管理画面にリダイレクト
if ($logged_in && $is_admin) {
    header('Location: admin/index.php');
    exit;
}

// ユーザーの初回ログインか確認
$requires_password_change = false;
if ($logged_in && !$_SESSION['user']['is_admin']) {
    $users = get_users();
    foreach ($users as $user) {
        if ($user['id'] === $_SESSION['user']['id']) {
            if (isset($user['first_login']) && $user['first_login'] === true) {
                $requires_password_change = true;
            }
            break;
        }
    }
}

// ユーザーのリンク一覧を取得
$user_links = [];
if ($logged_in && !$requires_password_change) {
    $links = get_links();
    foreach ($links as $link) {
        if ($link['user_id'] === $_SESSION['user']['id']) {
            $user_links[] = $link;
        }
    }
}

// パフォーマンス向上のため、バックアップを定期的に作成（ここではアクセス毎にバックアップを作成）
create_backup();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>サムネイル付きリンク生成サービス</title>
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
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
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
        input[type="text"],
        input[type="url"],
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
        input[type="url"]:focus,
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
        .success-message input[type="text"] {
            width: 70%;
            margin-top: 10px;
            display: inline-block;
            background-color: #2a2a2a;
        }
        .success-message button {
            width: 25%;
            margin-left: 5%;
            display: inline-block;
            padding: 10px;
        }
        .preview-image {
            max-width: 100%;
            border-radius: 5px;
            margin-top: 20px;
            animation: fadeIn 0.5s;
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
        /* テンプレート画像のグリッド表示 */
        .template-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
        }
        .template-item {
            width: 45%;
            margin: 2.5%;
            position: relative;
            overflow: hidden;
            border-radius: 5px;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .template-item:hover {
            transform: scale(1.05);
        }
        .template-item img {
            width: 100%;
            border-radius: 5px;
        }
        .template-item input[type="radio"] {
            position: absolute;
            top: 10px;
            left: 10px;
            transform: scale(1.5);
        }
        /* 詳細設定のスタイル */
        .details-section {
            display: none;
            margin-top: 20px;
            animation: fadeIn 0.5s;
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
        /* 画像選択ボタンのスタイル */
        .image-option-buttons {
            display: flex;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .image-option-button {
            background: linear-gradient(to right, #00e5ff, #00b0ff);
            color: #000;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            padding: 10px;
            margin: 5px;
            cursor: pointer;
            flex: 1;
            text-align: center;
            transition: transform 0.2s;
        }
        .image-option-button:hover {
            background: linear-gradient(to right, #00b0ff, #00e5ff);
            transform: scale(1.02);
        }
        .image-option-button.active {
            background: #00e5ff;
        }
        /* ボタン間隔の調整 */
        .image-option-buttons .image-option-button {
            margin: 5px;
        }
        /* メディアクエリ */
        @media screen and (max-width: 600px) {
            .success-message input[type="text"] {
                width: 100%;
                margin-bottom: 10px;
            }
            .success-message button {
                width: 100%;
                margin-left: 0;
            }
            .image-option-button {
                flex: 1 1 100%;
            }
        }
    </style>
    <script>
        // JavaScriptをここに記述
        document.addEventListener('DOMContentLoaded', function() {
            let selectedImageOption = '';

            // 画像選択方法のボタン処理
            const imageOptionButtons = document.querySelectorAll('.image-option-button');
            imageOptionButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // クラスの切り替え
                    imageOptionButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    selectedImageOption = this.dataset.option;
                    document.getElementById('imageOptionInput').value = selectedImageOption;

                    // 各オプションの表示・非表示
                    document.getElementById('imageUrlInput').style.display = 'none';
                    document.getElementById('imageFileInput').style.display = 'none';

                    if (selectedImageOption === 'url') {
                        document.getElementById('imageUrlInput').style.display = 'block';
                    } else if (selectedImageOption === 'upload') {
                        document.getElementById('imageFileInput').style.display = 'block';
                    } else if (selectedImageOption === 'template') {
                        // テンプレート選択モーダルを表示
                        openTemplateModal();
                    }
                });
            });

            // 詳細設定の表示・非表示
            const detailsButton = document.getElementById('toggleDetails');
            const detailsSection = document.getElementById('detailsSection');
            detailsButton.addEventListener('click', function() {
                if (detailsSection.style.display === 'none') {
                    detailsSection.style.display = 'block';
                } else {
                    detailsSection.style.display = 'none';
                }
            });

            // クリップボードコピー機能
            const copyButton = document.getElementById('copyButton');
            if (copyButton) {
                copyButton.addEventListener('click', function() {
                    const copyText = document.getElementById('generatedLink');
                    copyText.select();
                    copyText.setSelectionRange(0, 99999);
                    document.execCommand('copy');
                    alert('リンクをコピーしました。');
                });
            }

            // 画像プレビューと切り抜き処理
            const imageFileInput = document.querySelector('input[name="imageFile"]');
            if (imageFileInput) {
                imageFileInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            loadImageAndCrop(e.target.result);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            const imageUrlInput = document.querySelector('input[name="imageUrl"]');
            if (imageUrlInput) {
                imageUrlInput.addEventListener('blur', function() {
                    const url = this.value;
                    if (url) {
                        loadImageAndCrop(url, true);
                    }
                });
            }

            function loadImageAndCrop(source, isUrl = false) {
                const img = new Image();
                img.crossOrigin = "Anonymous"; // CORS対策
                img.onload = function() {
                    // 2:1に切り抜き
                    const canvas = document.createElement('canvas');
                    const desiredWidth = img.width;
                    const desiredHeight = img.width / 2; // アスペクト比2:1
                    canvas.width = desiredWidth;
                    canvas.height = desiredHeight;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, desiredWidth, desiredHeight);
                    const dataURL = canvas.toDataURL('image/png');
                    // プレビュー表示
                    let preview = document.getElementById('imagePreview');
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.id = 'imagePreview';
                        preview.classList.add('preview-image');
                        document.querySelector('.container').appendChild(preview);
                    }
                    preview.src = dataURL;
                    // editedImageDataにセット
                    document.getElementById('editedImageData').value = dataURL;
                };
                img.onerror = function() {
                    alert('画像を読み込めませんでした。');
                };
                if (isUrl) {
                    img.src = source;
                } else {
                    img.src = source;
                }
            }

            // テンプレート選択モーダルの処理
            const templateModal = document.getElementById('templateModal');
            const templateClose = document.getElementById('templateClose');
            const templateItems = document.querySelectorAll('.template-item');

            function openTemplateModal() {
                templateModal.style.display = 'block';
            }

            templateClose.addEventListener('click', function() {
                templateModal.style.display = 'none';
            });
            window.addEventListener('click', function(event) {
                if (event.target == templateModal) {
                    templateModal.style.display = 'none';
                }
            });
            templateItems.forEach(item => {
                item.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    templateModal.style.display = 'none';
                    // プレビュー表示
                    let preview = document.getElementById('imagePreview');
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.id = 'imagePreview';
                        preview.classList.add('preview-image');
                        document.querySelector('.container').appendChild(preview);
                    }
                    preview.src = this.querySelector('img').src;
                    // サーバーに送信するselectedTemplateの値を設定
                    document.getElementById('selectedTemplateInput').value = radio.value;
                });
            });

            // パスワード変更フォームの表示
            <?php if ($requires_password_change): ?>
                alert('初回ログインのため、パスワードを変更してください。');
            <?php endif; ?>
        </script>
</head>
<body>
    <div class="container">
        <h1>サムネイル付きリンク生成サービス</h1>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message">
                <p>リンクが生成されました：</p>
                <input type="text" id="generatedLink" value="<?php echo $generatedLink; ?>" readonly>
                <button id="copyButton">コピー</button>
                <p>保存しない場合、再度登録が必要です。</p>
            </div>
        <?php endif; ?>

        <?php if (!$logged_in): ?>
            <!-- ログインフォーム -->
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <label>ユーザーID</label>
                <input type="text" name="username" required>

                <label>パスワード</label>
                <input type="password" name="password" required>

                <button type="submit" name="login">ログイン</button>
            </form>
        <?php elseif ($requires_password_change): ?>
            <!-- パスワード変更フォーム -->
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <label>新しいパスワード</label>
                <input type="password" name="new_password" required>

                <label>新しいパスワード（確認）</label>
                <input type="password" name="confirm_password" required>

                <button type="submit" name="change_password">パスワードを変更</button>
            </form>
        <?php else: ?>
            <!-- ユーザーダッシュボード -->
            <a href="?id=123&action=logout" style="color: #00e5ff; text-decoration: none;">ログアウト</a>

            <!-- リンク生成フォーム -->
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" id="editedImageData" name="editedImageData">
                <input type="hidden" id="imageOptionInput" name="imageOption" required>
                <input type="hidden" id="selectedTemplateInput" name="selectedTemplate">

                <label>遷移先URL（必須）</label>
                <input type="url" name="linkA" required>

                <label>タイトル（必須）</label>
                <input type="text" name="title" required>

                <label>サムネイル画像の選択方法（必須）</label>
                <div class="image-option-buttons">
                    <button type="button" class="image-option-button" data-option="url">画像URLを入力</button>
                    <button type="button" class="image-option-button" data-option="upload">画像ファイルをアップロード</button>
                </div>
                <div class="image-option-buttons">
                    <button type="button" class="image-option-button" data-option="template">テンプレートから選択</button>
                </div>

                <div id="imageUrlInput" style="display:none;">
                    <label>画像URLを入力</label>
                    <input type="url" name="imageUrl">
                </div>

                <div id="imageFileInput" style="display:none;">
                    <label>画像ファイルをアップロード</label>
                    <input type="file" name="imageFile" accept="image/*">
                </div>

                <!-- テンプレート選択モーダル -->
                <div id="templateModal" class="modal">
                    <div class="modal-content">
                        <span class="close" id="templateClose">&times;</span>
                        <h2>テンプレートを選択</h2>
                        <div class="template-grid">
                            <?php
                            $templates = ['live_now.png', 'nude.png', 'gigafile.jpg', 'ComingSoon.png'];
                            foreach ($templates as $template):
                            ?>
                                <div class="template-item">
                                    <img src="temp/<?php echo $template; ?>" alt="<?php echo $template; ?>">
                                    <input type="radio" name="templateRadio" value="<?php echo $template; ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <button type="button" id="toggleDetails">詳細設定</button>
                <div id="detailsSection" class="details-section">
                    <label>ページの説明</label>
                    <textarea name="description"></textarea>

                    <label>Twitterアカウント名（@を含む）</label>
                    <input type="text" name="twitterSite">

                    <label>画像の代替テキスト</label>
                    <input type="text" name="imageAlt">
                </div>

                <button type="submit">リンクを生成</button>
            </form>

            <!-- ユーザーのリンク一覧 -->
            <h2 style="margin-top: 40px;">あなたの生成したリンク一覧</h2>
            <input type="text" id="searchInput" placeholder="検索...">
            <table style="width:100%; border-collapse: collapse; margin-top: 10px;">
                <thead>
                    <tr>
                        <th style="border: 1px solid #ffffff; padding: 8px;">タイトル</th>
                        <th style="border: 1px solid #ffffff; padding: 8px;">リンク</th>
                        <th style="border: 1px solid #ffffff; padding: 8px;">アクション</th>
                    </tr>
                </thead>
                <tbody id="linksTable">
                    <?php foreach ($user_links as $link): ?>
                        <tr>
                            <td style="border: 1px solid #ffffff; padding: 8px;"><?php echo htmlspecialchars($link['title']); ?></td>
                            <td style="border: 1px solid #ffffff; padding: 8px;">
                                <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" style="color: #00e5ff;"><?php echo htmlspecialchars($link['url']); ?></a>
                            </td>
                            <td style="border: 1px solid #ffffff; padding: 8px;">
                                <button type="button" onclick="editLink('<?php echo htmlspecialchars($link['id']); ?>')">編集</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- 編集リンクモーダル -->
            <div id="editModal" class="modal">
                <div class="modal-content">
                    <span class="close" id="editClose">&times;</span>
                    <h2>リンクを編集</h2>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" id="editLinkId" name="edit_link_id">

                        <label>遷移先URL（必須）</label>
                        <input type="url" name="edit_linkA" id="edit_linkA" required>

                        <label>タイトル（必須）</label>
                        <input type="text" name="edit_title" id="edit_title" required>

                        <label>サムネイル画像の選択方法（必須）</label>
                        <div class="image-option-buttons">
                            <button type="button" class="image-option-button" data-option="url" onclick="selectEditOption('url')">画像URLを入力</button>
                            <button type="button" class="image-option-button" data-option="upload" onclick="selectEditOption('upload')">画像ファイルをアップロード</button>
                        </div>
                        <div class="image-option-buttons">
                            <button type="button" class="image-option-button" data-option="template" onclick="selectEditOption('template')">テンプレートから選択</button>
                        </div>

                        <div id="editImageUrlInput" style="display:none;">
                            <label>画像URLを入力</label>
                            <input type="url" name="edit_imageUrl" id="edit_imageUrl">
                        </div>

                        <div id="editImageFileInput" style="display:none;">
                            <label>画像ファイルをアップロード</label>
                            <input type="file" name="edit_imageFile" id="edit_imageFile" accept="image/*">
                        </div>

                        <!-- テンプレート選択モーダル -->
                        <div id="editTemplateModal" class="modal">
                            <div class="modal-content">
                                <span class="close" id="editTemplateClose">&times;</span>
                                <h2>テンプレートを選択</h2>
                                <div class="template-grid">
                                    <?php
                                    foreach ($templates as $template):
                                    ?>
                                        <div class="template-item">
                                            <img src="temp/<?php echo $template; ?>" alt="<?php echo $template; ?>">
                                            <input type="radio" name="edit_templateRadio" value="<?php echo $template; ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="edit_link">変更を保存</button>
                    </form>
                </div>
            </div>

            <!-- JavaScript for Edit Modal -->
            <script>
                function editLink(linkId) {
                    document.getElementById('editModal').style.display = 'block';
                    document.getElementById('editLinkId').value = linkId;

                    // Fetch link data via AJAX
                    fetch('get_link.php?id=' + linkId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                document.getElementById('edit_linkA').value = data.link.url;
                                document.getElementById('edit_title').value = data.link.title;
                                // Set the image preview
                                if (data.link.image_option === 'url') {
                                    selectEditOption('url');
                                    document.getElementById('edit_imageUrl').value = data.link.image_url;
                                } else if (data.link.image_option === 'upload') {
                                    selectEditOption('upload');
                                    // Cannot set file input value for security reasons
                                } else if (data.link.image_option === 'template') {
                                    selectEditOption('template');
                                    // Set selected template
                                    // This can be implemented if necessary
                                }
                            } else {
                                alert('リンクデータの取得に失敗しました。');
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching link data:', error);
                            alert('リンクデータの取得中にエラーが発生しました。');
                        });
                }

                // 閉じるボタンの処理
                const editModal = document.getElementById('editModal');
                const editClose = document.getElementById('editClose');
                const editTemplateModal = document.getElementById('editTemplateModal');
                const editTemplateClose = document.getElementById('editTemplateClose');

                editClose.addEventListener('click', function() {
                    editModal.style.display = 'none';
                });
                editTemplateClose.addEventListener('click', function() {
                    editTemplateModal.style.display = 'none';
                });
                window.addEventListener('click', function(event) {
                    if (event.target == editModal) {
                        editModal.style.display = 'none';
                    }
                    if (event.target == editTemplateModal) {
                        editTemplateModal.style.display = 'none';
                    }
                });

                function selectEditOption(option) {
                    // Remove active class from all buttons
                    const buttons = document.querySelectorAll('#editModal .image-option-button');
                    buttons.forEach(btn => btn.classList.remove('active'));
                    // Add active class to selected button
                    buttons.forEach(btn => {
                        if (btn.dataset.option === option) {
                            btn.classList.add('active');
                        }
                    });

                    document.getElementById('editImageUrlInput').style.display = 'none';
                    document.getElementById('editImageFileInput').style.display = 'none';

                    if (option === 'url') {
                        document.getElementById('editImageUrlInput').style.display = 'block';
                    } else if (option === 'upload') {
                        document.getElementById('editImageFileInput').style.display = 'block';
                    } else if (option === 'template') {
                        // テンプレート選択モーダルを表示
                        editTemplateModal.style.display = 'block';
                    }
                }

                // テンプレート選択モーダルの処理
                const editTemplateItems = document.querySelectorAll('.template-item');
                editTemplateItems.forEach(item => {
                    item.addEventListener('click', function() {
                        const radio = this.querySelector('input[type="radio"]');
                        radio.checked = true;
                        editTemplateModal.style.display = 'none';
                        // プレビュー表示
                        let preview = document.getElementById('imagePreview');
                        if (!preview) {
                            preview = document.createElement('img');
                            preview.id = 'imagePreview';
                            preview.classList.add('preview-image');
                            document.querySelector('.container').appendChild(preview);
                        }
                        preview.src = this.querySelector('img').src;
                        // サーバーに送信するselectedTemplateの値を設定
                        document.getElementById('selectedTemplateInput').value = radio.value;
                    });
                });
            </script>

            <?php
            // リンク生成処理
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$requires_password_change) {
                if (isset($_POST['edit_link'])) {
                    // リンク編集処理
                    $edit_link_id = htmlspecialchars(trim($_POST['edit_link_id']));
                    $edit_linkA = filter_var(trim($_POST['edit_linkA']), FILTER_SANITIZE_URL);
                    $edit_title = htmlspecialchars(trim($_POST['edit_title']), ENT_QUOTES, 'UTF-8');
                    $edit_image_option = htmlspecialchars(trim($_POST['imageOption']), ENT_QUOTES, 'UTF-8');
                    $edit_selected_template = htmlspecialchars(trim($_POST['selectedTemplate']), ENT_QUOTES, 'UTF-8');
                    $edit_imageUrl = isset($_POST['edit_imageUrl']) ? filter_var(trim($_POST['edit_imageUrl']), FILTER_SANITIZE_URL) : '';
                    $edit_imageData = isset($_POST['editedImageData']) ? $_POST['editedImageData'] : '';
    
                    // バリデーション
                    if (empty($edit_linkA) || !filter_var($edit_linkA, FILTER_VALIDATE_URL)) {
                        echo '<script>alert("有効な遷移先URLを入力してください。");</script>';
                    } elseif (empty($edit_title)) {
                        echo '<script>alert("タイトルを入力してください。");</script>';
                    } elseif (!in_array($edit_image_option, ['url', 'upload', 'template'])) {
                        echo '<script>alert("サムネイル画像の選択方法を選んでください。");</script>';
                    } else {
                        $links = get_links();
                        $link_found = false;
                        foreach ($links as &$link) {
                            if ($link['id'] === $edit_link_id && $link['user_id'] === $_SESSION['user']['id']) {
                                $link['url'] = $edit_linkA;
                                $link['title'] = $edit_title;
                                $link['image_option'] = $edit_image_option;
                                if ($edit_image_option === 'url') {
                                    $link['image_url'] = $edit_imageUrl;
                                    $link['image_path'] = '';
                                } elseif ($edit_image_option === 'upload') {
                                    if (!empty($edit_imageData)) {
                                        $link['image_path'] = saveImage(base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $edit_imageData)));
                                        $link['image_url'] = '';
                                    }
                                } elseif ($edit_image_option === 'template') {
                                    $link['image_path'] = 'temp/' . $edit_selected_template;
                                    $link['image_url'] = '';
                                }
                                $link_found = true;
                                break;
                            }
                        }
                        if ($link_found) {
                            save_links($links);
                            echo '<script>alert("リンクが正常に更新されました。"); window.location.reload();</script>';
                            log_event($_SESSION['user']['id'], "Link updated: $edit_link_id");
                        } else {
                            echo '<script>alert("リンクの更新に失敗しました。");</script>';
                        }
                    }
                } else {
                    // リンク生成処理
                    $linkA = filter_var(trim($_POST['linkA']), FILTER_SANITIZE_URL);
                    $title = htmlspecialchars(trim($_POST['title']), ENT_QUOTES, 'UTF-8');
                    $image_option = htmlspecialchars(trim($_POST['imageOption']), ENT_QUOTES, 'UTF-8');
                    $selected_template = htmlspecialchars(trim($_POST['selectedTemplate']), ENT_QUOTES, 'UTF-8');
                    $imageUrl = isset($_POST['imageUrl']) ? filter_var(trim($_POST['imageUrl']), FILTER_SANITIZE_URL) : '';
                    $imageData = isset($_POST['editedImageData']) ? $_POST['editedImageData'] : '';

                    // バリデーション
                    if (empty($linkA) || !filter_var($linkA, FILTER_VALIDATE_URL)) {
                        echo '<script>alert("有効な遷移先URLを入力してください。");</script>';
                    } elseif (empty($title)) {
                        echo '<script>alert("タイトルを入力してください。");</script>';
                    } elseif (!in_array($image_option, ['url', 'upload', 'template'])) {
                        echo '<script>alert("サムネイル画像の選択方法を選んでください。");</script>';
                    } else {
                        $image_path = '';
                        if ($image_option === 'url') {
                            if (empty($imageUrl) || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                                echo '<script>alert("有効な画像URLを入力してください。");</script>';
                                exit;
                            }
                            // 画像を取得して保存
                            $image_content = file_get_contents_curl($imageUrl);
                            if ($image_content === false) {
                                echo '<script>alert("画像を取得できませんでした。");</script>';
                                exit;
                            }
                            $image_path = saveImage($image_content);
                        } elseif ($image_option === 'upload') {
                            if (empty($imageData)) {
                                echo '<script>alert("画像のアップロードに失敗しました。");</script>';
                                exit;
                            }
                            $image_content = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imageData));
                            $image_path = saveImage($image_content);
                        } elseif ($image_option === 'template') {
                            $templateImages = ['live_now.png', 'nude.png', 'gigafile.jpg', 'ComingSoon.png'];
                            if (!in_array($selected_template, $templateImages)) {
                                echo '<script>alert("有効なテンプレート画像を選択してください。");</script>';
                                exit;
                            }
                            $image_path = 'temp/' . $selected_template;
                        }

                        // リンク情報を保存
                        $links = get_links();
                        $new_link = [
                            'id' => uniqid(),
                            'user_id' => $_SESSION['user']['id'],
                            'url' => $linkA,
                            'title' => $title,
                            'image_option' => $image_option,
                            'image_url' => ($image_option === 'url') ? $imageUrl : '',
                            'image_path' => $image_path,
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        $links[] = $new_link;
                        save_links($links);

                        echo '<script>alert("リンクが正常に生成されました。"); window.location.reload();</script>';
                        log_event($_SESSION['user']['id'], "Link created: " . $new_link['id']);
                    }
                }
            }
            ?>

            <?php if ($logged_in && !$requires_password_change): ?>
                <!-- ユーザーのリンク一覧を表示するJavaScript -->
                <script>
                    document.getElementById('searchInput').addEventListener('keyup', function() {
                        let filter = this.value.toUpperCase();
                        let table = document.getElementById('linksTable');
                        let tr = table.getElementsByTagName('tr');
                        for (let i = 0; i < tr.length; i++) {
                            let tdTitle = tr[i].getElementsByTagName('td')[0];
                            let tdLink = tr[i].getElementsByTagName('td')[1];
                            if (tdTitle && tdLink) {
                                let txtValueTitle = tdTitle.textContent || tdTitle.innerText;
                                let txtValueLink = tdLink.textContent || tdLink.innerText;
                                if (txtValueTitle.toUpperCase().indexOf(filter) > -1 || txtValueLink.toUpperCase().indexOf(filter) > -1) {
                                    tr[i].style.display = "";
                                } else {
                                    tr[i].style.display = "none";
                                }
                            }
                        }
                    });
                </script>
            <?php endif; ?>
    </div>
</body>
</html>
