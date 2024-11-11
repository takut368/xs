<?php
session_start();

// セキュリティ対策: セッションハイジャック防止
ini_set('session.cookie_httponly', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

// 初期化
$errors = [];
$success = false;
$generatedLink = '';

// ユーザー認証チェック
function is_logged_in() {
    return isset($_SESSION['user']);
}

function is_admin() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}

// パスの定義
define('USERS_FILE', __DIR__ . '/users.json');
define('LINKS_FILE', __DIR__ . '/links.json');
define('UPLOADS_DIR', __DIR__ . '/uploads/');
define('TEMPLATES_DIR', __DIR__ . '/templates/');
define('LOGS_DIR', __DIR__ . '/logs/');

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

// ユーザー登録（初期管理者の作成）
$users = get_users();
if (count($users) === 0) {
    // 初期管理者の作成
    $initial_admin = [
        'id' => 'admin',
        'password' => hash_password('admin123'), // デフォルトパスワードは変更してください
        'role' => 'admin'
    ];
    $users[] = $initial_admin;
    save_users($users);
    log_action('Initial Admin Created', 'ID: admin');
}

// ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    // CSRFトークンチェック
    if (!isset($_POST['token']) || !hash_equals($_SESSION['token'], $_POST['token'])) {
        $errors[] = '不正なリクエストです。';
    } else {
        $id = trim($_POST['id']);
        $password = $_POST['password'];

        if (empty($id) || empty($password)) {
            $errors[] = 'IDとパスワードを入力してください。';
        } else {
            foreach ($users as $user) {
                if ($user['id'] === $id && verify_password($password, $user['password'])) {
                    $_SESSION['user'] = [
                        'id' => $user['id'],
                        'role' => $user['role']
                    ];
                    log_action('User Logged In', "ID: $id");
                    header('Location: index.php');
                    exit;
                }
            }
            $errors[] = 'IDまたはパスワードが正しくありません。';
            log_action('Failed Login Attempt', "ID: $id");
        }
    }
}

// ログアウト処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'logout') {
    if (is_logged_in()) {
        log_action('User Logged Out', "ID: " . $_SESSION['user']['id']);
    }
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

// リンク生成処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_link') {
    if (!is_logged_in()) {
        $errors[] = 'ログインが必要です。';
    } else {
        // CSRFトークンチェック
        if (!isset($_POST['token']) || !hash_equals($_SESSION['token'], $_POST['token'])) {
            $errors[] = '不正なリクエストです。';
        } else {
            $linkA = filter_input(INPUT_POST, 'linkA', FILTER_SANITIZE_URL);
            $title = htmlspecialchars(trim($_POST['title']), ENT_QUOTES, 'UTF-8');
            $description = htmlspecialchars(trim($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8');
            $twitterSite = htmlspecialchars(trim($_POST['twitterSite'] ?? ''), ENT_QUOTES, 'UTF-8');
            $imageAlt = htmlspecialchars(trim($_POST['imageAlt'] ?? ''), ENT_QUOTES, 'UTF-8');
            $imageOption = $_POST['imageOption'] ?? '';
            $selectedTemplate = $_POST['selectedTemplate'] ?? '';
            $editedImageData = $_POST['editedImageData'] ?? '';

            // バリデーション
            if (empty($linkA) || !filter_var($linkA, FILTER_VALIDATE_URL)) {
                $errors[] = '有効な遷移先URLを入力してください。';
            }
            if (empty($title)) {
                $errors[] = 'タイトルを入力してください。';
            }
            if (!in_array($imageOption, ['url', 'upload', 'template'])) {
                $errors[] = 'サムネイル画像の選択方法を選んでください。';
            }

            // サムネイル画像の処理
            if (empty($errors)) {
                $imagePath = '';

                if ($imageOption === 'template') {
                    // テンプレート画像を使用
                    $templateImages = ['live_now.png', 'nude.png', 'gigafile.jpg', 'ComingSoon.png'];
                    if (!in_array($selectedTemplate, $templateImages)) {
                        $errors[] = '有効なテンプレート画像を選択してください。';
                    } else {
                        $imagePath = 'templates/' . $selectedTemplate;
                    }
                }

                // 画像編集テンプレートの適用またはクライアント側で処理された画像の保存
                if (!empty($editedImageData)) {
                    $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $editedImageData));
                    $imagePath = saveImage($imageData);
                }

                if (empty($imagePath)) {
                    $errors[] = '画像の処理に失敗しました。';
                }

                // リンク情報の保存
                if (empty($errors)) {
                    $links = get_links();
                    $unique_id = uniqid();
                    $links[] = [
                        'id' => $unique_id,
                        'user_id' => $_SESSION['user']['id'],
                        'linkA' => $linkA,
                        'title' => $title,
                        'description' => $description,
                        'twitterSite' => $twitterSite,
                        'imageAlt' => $imageAlt,
                        'imagePath' => $imagePath,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    save_links($links);

                    // リンク用フォルダと index.php の作成
                    $linkDir = __DIR__ . '/' . $unique_id;
                    if (!mkdir($linkDir, 0777, true)) {
                        $errors[] = 'リンクフォルダの作成に失敗しました。';
                        // リンク情報を削除
                        array_pop($links);
                        save_links($links);
                    } else {
                        $indexFile = $linkDir . '/index.php';
                        $htmlContent = generate_redirect_page($linkA, $title, $description, $twitterSite, $imageAlt, $imagePath);
                        file_put_contents($indexFile, $htmlContent);
                        $generatedLink = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $unique_id;
                        $success = true;
                        log_action('Link Generated', "ID: $unique_id, User: " . $_SESSION['user']['id']);
                    }
                }
            }
        }
    }
}

// リダイレクトページ生成関数
function generate_redirect_page($linkA, $title, $description, $twitterSite, $imageAlt, $imagePath) {
    $imageUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $imagePath;
    $metaDescription = !empty($description) ? $description : $title;
    $twitterSiteTag = !empty($twitterSite) ? '<meta name="twitter:site" content="' . $twitterSite . '">' : '';
    $imageAltTag = !empty($imageAlt) ? $imageAlt : $title;

    $html = '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>' . $title . '</title>
    <meta name="description" content="' . $metaDescription . '">
    <meta http-equiv="refresh" content="0; URL=' . htmlspecialchars($linkA, ENT_QUOTES, 'UTF-8') . '">
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    ' . $twitterSiteTag . '
    <meta name="twitter:title" content="' . $title . '">
    <meta name="twitter:description" content="' . $metaDescription . '">
    <meta name="twitter:image" content="' . $imageUrl . '">
    <meta name="twitter:image:alt" content="' . $imageAltTag . '">
</head>
<body>
    <p>リダイレクト中...</p>
</body>
</html>';

    return $html;
}

// ユーザーのリンク一覧取得
function get_user_links($user_id) {
    $links = get_links();
    $user_links = [];
    foreach ($links as $link) {
        if ($link['user_id'] === $user_id) {
            $user_links[] = $link;
        }
    }
    return $user_links;
}

// リンク編集処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_link') {
    if (!is_logged_in()) {
        $errors[] = 'ログインが必要です。';
    } else {
        // CSRFトークンチェック
        if (!isset($_POST['token']) || !hash_equals($_SESSION['token'], $_POST['token'])) {
            $errors[] = '不正なリクエストです。';
        } else {
            $link_id = $_POST['link_id'];
            $linkA = filter_input(INPUT_POST, 'linkA', FILTER_SANITIZE_URL);
            $title = htmlspecialchars(trim($_POST['title']), ENT_QUOTES, 'UTF-8');
            $description = htmlspecialchars(trim($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8');
            $twitterSite = htmlspecialchars(trim($_POST['twitterSite'] ?? ''), ENT_QUOTES, 'UTF-8');
            $imageAlt = htmlspecialchars(trim($_POST['imageAlt'] ?? ''), ENT_QUOTES, 'UTF-8');
            $imageOption = $_POST['imageOption'] ?? '';
            $selectedTemplate = $_POST['selectedTemplate'] ?? '';
            $editedImageData = $_POST['editedImageData'] ?? '';

            // バリデーション
            if (empty($linkA) || !filter_var($linkA, FILTER_VALIDATE_URL)) {
                $errors[] = '有効な遷移先URLを入力してください。';
            }
            if (empty($title)) {
                $errors[] = 'タイトルを入力してください。';
            }
            if (!in_array($imageOption, ['url', 'upload', 'template'])) {
                $errors[] = 'サムネイル画像の選択方法を選んでください。';
            }

            // サムネイル画像の処理
            if (empty($errors)) {
                $imagePath = '';

                if ($imageOption === 'template') {
                    // テンプレート画像を使用
                    $templateImages = ['live_now.png', 'nude.png', 'gigafile.jpg', 'ComingSoon.png'];
                    if (!in_array($selectedTemplate, $templateImages)) {
                        $errors[] = '有効なテンプレート画像を選択してください。';
                    } else {
                        $imagePath = 'templates/' . $selectedTemplate;
                    }
                }

                // 画像編集テンプレートの適用またはクライアント側で処理された画像の保存
                if (!empty($editedImageData)) {
                    $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $editedImageData));
                    $imagePath = saveImage($imageData);
                }

                if (empty($imagePath)) {
                    $errors[] = '画像の処理に失敗しました。';
                }

                // リンク情報の更新
                if (empty($errors)) {
                    $links = get_links();
                    $found = false;
                    foreach ($links as &$link) {
                        if ($link['id'] === $link_id && $link['user_id'] === $_SESSION['user']['id']) {
                            $found = true;
                            $link['linkA'] = $linkA;
                            $link['title'] = $title;
                            $link['description'] = $description;
                            $link['twitterSite'] = $twitterSite;
                            $link['imageAlt'] = $imageAlt;
                            $link['imagePath'] = $imagePath;
                            $link['updated_at'] = date('Y-m-d H:i:s');

                            // 既存のリダイレクトページを更新
                            $linkDir = __DIR__ . '/' . $link_id;
                            $indexFile = $linkDir . '/index.php';
                            $htmlContent = generate_redirect_page($linkA, $title, $description, $twitterSite, $imageAlt, $imagePath);
                            file_put_contents($indexFile, $htmlContent);

                            $success = true;
                            $action_message = "リンクを更新しました。";
                            log_action('Link Edited', "ID: $link_id, User: " . $_SESSION['user']['id']);
                            break;
                        }
                    }
                    unset($link);

                    if ($found) {
                        save_links($links);
                    } else {
                        $errors[] = 'リンクが見つかりません。';
                    }
                }
            }
        }
    }
}

// ユーザーのリンク削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_link') {
    if (!is_logged_in()) {
        $errors[] = 'ログインが必要です。';
    } else {
        // CSRFトークンチェック
        if (!isset($_POST['token']) || !hash_equals($_SESSION['token'], $_POST['token'])) {
            $errors[] = '不正なリクエストです。';
        } else {
            $link_id = $_POST['link_id'];
            $links = get_links();
            $found = false;
            foreach ($links as $index => $link) {
                if ($link['id'] === $link_id && $link['user_id'] === $_SESSION['user']['id']) {
                    $found = true;
                    // リンクフォルダの削除
                    $linkDir = __DIR__ . '/' . $link_id;
                    if (is_dir($linkDir)) {
                        array_map('unlink', glob("$linkDir/*.*"));
                        rmdir($linkDir);
                    }
                    // リンク情報の削除
                    array_splice($links, $index, 1);
                    save_links($links);
                    $success = true;
                    $action_message = "リンクを削除しました。";
                    log_action('Link Deleted', "ID: $link_id, User: " . $_SESSION['user']['id']);
                    break;
                }
            }

            if (!$found) {
                $errors[] = 'リンクが見つかりません。';
            }
        }
    }
}

// HTML出力
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
        /* リンク一覧スタイル */
        .link-list {
            margin-top: 30px;
        }
        .link-item {
            background-color: #1e1e1e;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            animation: fadeInUp 0.3s;
        }
        .link-item h3 {
            margin-bottom: 10px;
        }
        .link-item img {
            max-width: 100%;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .link-actions {
            display: flex;
            justify-content: space-between;
        }
        .link-actions form {
            display: inline;
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

        });
    </script>
</head>
<body>
    <div class="container">
        <?php echo $logout_link; ?>

        <?php if (!is_logged_in()): ?>
            <h1>ログイン</h1>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                <label>ID</label>
                <input type="text" name="id" required>

                <label>パスワード</label>
                <input type="password" name="password" required>

                <button type="submit">ログイン</button>
            </form>
        <?php else: ?>
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
                </div>
            <?php endif; ?>

            <h1>リンク生成</h1>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="generate_link">
                <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
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
                                    <img src="templates/<?php echo $template; ?>" alt="<?php echo $template; ?>">
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

            <h2>あなたの生成したリンク一覧</h2>
            <div class="link-list">
                <?php if (count($user_links) === 0): ?>
                    <p>まだリンクは生成されていません。</p>
                <?php else: ?>
                    <?php foreach ($user_links as $link): ?>
                        <div class="link-item">
                            <h3><?php echo htmlspecialchars($link['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <img src="<?php echo htmlspecialchars($link['imagePath'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($link['imageAlt'], ENT_QUOTES, 'UTF-8'); ?>">
                            <p>リンク: <a href="<?php echo htmlspecialchars($link['linkA'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank"><?php echo htmlspecialchars($link['linkA'], ENT_QUOTES, 'UTF-8'); ?></a></p>
                            <div class="link-actions">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="edit_link">
                                    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                                    <input type="hidden" name="link_id" value="<?php echo $link['id']; ?>">
                                    <button type="submit">編集</button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('本当に削除しますか？');">
                                    <input type="hidden" name="action" value="delete_link">
                                    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                                    <input type="hidden" name="link_id" value="<?php echo $link['id']; ?>">
                                    <button type="submit" style="background-color: #ff5252;">削除</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
