<?php
// セッションの開始は一番最初に行う必要があります
session_start();

// アクセス制限: ?id=123 がない場合は空白ページを表示
if (!isset($_GET['id']) || $_GET['id'] !== '123') {
    // 空白ページ
    exit();
}

// データファイルのパス定義
define('USERS_FILE', 'users.json');
define('SEISEI_FILE', 'seisei.json');
define('ACCESS_LOG_FILE', 'access_logs.json');
define('BACKUP_DIR', 'backups');

// 管理者の資格情報（変更不可）
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin');

// データファイルの存在確認と初期化
if (!file_exists(USERS_FILE)) {
    file_put_contents(USERS_FILE, json_encode([]));
}
if (!file_exists(SEISEI_FILE)) {
    file_put_contents(SEISEI_FILE, json_encode([]));
}
if (!file_exists(ACCESS_LOG_FILE)) {
    file_put_contents(ACCESS_LOG_FILE, json_encode([]));
}
if (!file_exists(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0777, true);
}

// アクセスログを記録する関数
function log_access($user, $action) {
    $log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user' => $user,
        'action' => $action
    ];
    $logs = json_decode(file_get_contents(ACCESS_LOG_FILE), true);
    $logs[] = $log;
    file_put_contents(ACCESS_LOG_FILE, json_encode($logs, JSON_PRETTY_PRINT));
}

// データをバックアップする関数
function backup_data() {
    $files = [USERS_FILE, SEISEI_FILE, ACCESS_LOG_FILE];
    $backupFile = BACKUP_DIR . '/backup_' . date('Ymd_His') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($backupFile, ZipArchive::CREATE) === TRUE) {
        foreach ($files as $file) {
            if (file_exists($file)) {
                $zip->addFile($file, $file);
            }
        }
        $zip->close();
    }
}

// 入力をサニタイズする関数
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// リンクのユニークIDを生成する関数
function generate_unique_id() {
    return bin2hex(random_bytes(6));
}

// 現在のユーザー情報を取得する関数
function get_current_user() {
    $username = $_SESSION['user'] ?? ($_COOKIE['user'] ?? '');
    if ($username === ADMIN_USERNAME) {
        return 'admin';
    }
    $users = json_decode(file_get_contents(USERS_FILE), true);
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            return $user;
        }
    }
    return null;
}

// ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // CSRFトークンの検証（必要に応じて追加）

    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? ''; // パスワードはサニタイズしない

    // 管理者のログインチェック
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['user'] = $username;
        // 30日間のクッキーを設定
        setcookie('user', $username, time() + (30 * 24 * 60 * 60), "/", "", false, true);
        // アクセスログを記録
        log_access($username, 'login');
        // 管理者ダッシュボードにリダイレクト
        header('Location: admin/index.php');
        exit();
    } else {
        // ユーザーの認証
        $users = json_decode(file_get_contents(USERS_FILE), true);
        foreach ($users as $user) {
            if ($user['username'] === $username && password_verify($password, $user['password'])) {
                $_SESSION['user'] = $username;
                // 30日間のクッキーを設定
                setcookie('user', $username, time() + (30 * 24 * 60 * 60), "/", "", false, true);
                // アクセスログを記録
                log_access($username, 'login');
                // 初回ログインか確認
                if (isset($user['first_login']) && $user['first_login'] === true) {
                    header('Location: index.php?action=change_password');
                    exit();
                } else {
                    header('Location: index.php?action=dashboard');
                    exit();
                }
            }
        }
        // 認証失敗
        $error = 'IDまたはパスワードが正しくありません。';
        // 不正なログイン試行を記録
        log_access($username, 'failed_login');
    }
}

// ログアウト処理
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $username = $_SESSION['user'] ?? $_COOKIE['user'];
    if ($username) {
        log_access($username, 'logout');
    }
    // セッションとクッキーをクリア
    session_destroy();
    setcookie('user', '', time() - 3600, "/", "", false, true);
    header('Location: index.php');
    exit();
}

// ログイン状態の確認
if (isset($_SESSION['user']) || isset($_COOKIE['user'])) {
    $user_data = get_current_user();
    if ($user_data === 'admin') {
        header('Location: admin/index.php');
        exit();
    } elseif ($user_data !== null) {
        // ユーザーダッシュボードへリダイレクト
        if (isset($user_data['first_login']) && $user_data['first_login'] === true && !isset($_GET['action'])) {
            header('Location: index.php?action=change_password');
            exit();
        } else {
            header('Location: index.php?action=dashboard');
            exit();
        }
    } else {
        // 不正なユーザーの場合、セッションとクッキーをクリア
        session_destroy();
        setcookie('user', '', time() - 3600, "/", "", false, true);
    }
}

// パスワード変更処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $user = get_current_user();
    if ($user === null || $user === 'admin') {
        $error = '不正な操作です。';
    } else {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($new_password) || empty($confirm_password)) {
            $error = 'パスワードを入力してください。';
        } elseif ($new_password !== $confirm_password) {
            $error = 'パスワードが一致しません。';
        } elseif (strlen($new_password) < 8) {
            $error = 'パスワードは8文字以上で入力してください。';
        } else {
            // パスワードとfirst_loginを更新
            $users = json_decode(file_get_contents(USERS_FILE), true);
            foreach ($users as &$u) {
                if ($u['username'] === $user['username']) {
                    $u['password'] = password_hash($new_password, PASSWORD_BCRYPT);
                    $u['first_login'] = false;
                    break;
                }
            }
            file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
            // アクセスログを記録
            log_access($user['username'], 'password_changed');
            // ダッシュボードへリダイレクト
            header('Location: index.php?action=dashboard');
            exit();
        }
    }
}

// ダッシュボード表示
if (isset($_GET['action']) && $_GET['action'] === 'dashboard') {
    $user = get_current_user();
    if ($user === null || $user === 'admin') {
        header('Location: index.php');
        exit();
    }

    // リンクの生成・編集・削除を処理
    // 既に処理済みなのでここではデータを表示

    // リンク生成フォームからのデータ処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_link'])) {
        $linkA = filter_var($_POST['linkA'], FILTER_SANITIZE_URL);
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description'] ?? '');
        $twitterSite = sanitize($_POST['twitterSite'] ?? '');
        $imageAlt = sanitize($_POST['imageAlt'] ?? '');
        $imageOption = sanitize($_POST['imageOption'] ?? '');
        $selectedTemplate = sanitize($_POST['selectedTemplate'] ?? '');
        $editedImageData = $_POST['editedImageData'] ?? '';

        // バリデーション
        if (empty($linkA) || !filter_var($linkA, FILTER_VALIDATE_URL)) {
            $error = '有効な遷移先URLを入力してください。';
        } elseif (empty($title)) {
            $error = 'タイトルを入力してください。';
        } elseif (!in_array($imageOption, ['url', 'upload', 'template'])) {
            $error = 'サムネイル画像の選択方法を選んでください。';
        } else {
            // 画像処理
            $imagePath = '';
            if ($imageOption === 'template') {
                // テンプレート画像の使用
                $templateImages = ['live_now.png', 'nude.png', 'gigafile.jpg', 'ComingSoon.png'];
                if (!in_array($selectedTemplate, $templateImages)) {
                    $error = '有効なテンプレート画像を選択してください。';
                } else {
                    $imagePath = 'temp/' . $selectedTemplate;
                }
            }

            // 編集された画像データがある場合は保存
            if (!empty($editedImageData)) {
                $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $editedImageData));
                $imagePath = saveImage($imageData);
                if (!$imagePath) {
                    $error = '画像の保存に失敗しました。';
                }
            }

            if (empty($imagePath) && $imageOption !== 'template') {
                $error = '画像が選択されていません。';
            }

            if (empty($error)) {
                // ユニークなIDを生成
                $unique_id = generate_unique_id();

                // リンクデータを作成
                $link = [
                    'id' => $unique_id,
                    'username' => $user['username'],
                    'linkA' => $linkA,
                    'title' => $title,
                    'description' => $description,
                    'twitterSite' => $twitterSite,
                    'imageAlt' => $imageAlt,
                    'imagePath' => $imagePath,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                // seisei.jsonに保存
                $seisei = json_decode(file_get_contents(SEISEI_FILE), true);
                $seisei[] = $link;
                file_put_contents(SEISEI_FILE, json_encode($seisei, JSON_PRETTY_PRINT));

                // アクセスログを記録
                log_access($user['username'], 'link_generated: ' . $unique_id);

                // 成功メッセージ
                $success = 'リンクが正常に生成されました。';
            }
        }
    }

    // リンク一覧の取得
    $seisei = json_decode(file_get_contents(SEISEI_FILE), true);
    $user_links = array_filter($seisei, function($link) use ($user) {
        return $link['username'] === $user['username'];
    });

    // 検索・フィルタリング
    $search = sanitize($_GET['search'] ?? '');
    if (!empty($search)) {
        $user_links = array_filter($user_links, function($link) use ($search) {
            return stripos($link['title'], $search) !== false || stripos($link['linkA'], $search) !== false;
        });
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>サムネイル付きリンク生成サービス - ログイン</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* 全体のスタイルとアニメーション */
        body {
            background-color: #121212;
            color: #ffffff;
            font-family: 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 0;
            animation: fadeIn 1s ease-in-out;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            animation: slideIn 0.5s ease-in-out;
        }
        h1, h2, h3 {
            text-align: center;
            margin-bottom: 20px;
            animation: fadeInUp 0.5s;
        }
        form {
            background-color: #1e1e1e;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            animation: fadeIn 0.5s;
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
            padding: 10px;
            margin-top: 5px;
            border: none;
            border-radius: 5px;
            background-color: #2a2a2a;
            color: #ffffff;
            font-size: 16px;
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
            margin-top: 10px;
            animation: shake 0.5s;
        }
        .success {
            color: #00e676;
            font-size: 14px;
            margin-top: 10px;
            animation: fadeInUp 0.5s;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            animation: fadeIn 0.5s;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid #444;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #2a2a2a;
        }
        tr:nth-child(even) {
            background-color: #1e1e1e;
        }
        a {
            color: #00e5ff;
            text-decoration: none;
            transition: color 0.3s;
        }
        a:hover {
            color: #00b0ff;
        }
        .action-buttons button {
            background: linear-gradient(to right, #ff9800, #fb8c00);
            color: #000;
            border: none;
            border-radius: 5px;
            padding: 5px 10px;
            cursor: pointer;
            margin-right: 5px;
            transition: transform 0.2s;
        }
        .action-buttons button:hover {
            transform: scale(1.05);
        }
        /* モーダルスタイル */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            padding-top: 100px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.8);
            animation: fadeIn 0.5s;
        }
        .modal-content {
            background-color: #1e1e1e;
            margin: auto;
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
        .preview-image {
            max-width: 100%;
            border-radius: 5px;
            margin-top: 20px;
            display: none;
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
        @keyframes shake {
            0% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            50% { transform: translateX(5px); }
            75% { transform: translateX(-5px); }
            100% { transform: translateX(0); }
        }
        /* レスポンシブデザイン */
        @media screen and (max-width: 600px) {
            .action-buttons button {
                padding: 5px;
                font-size: 12px;
            }
            input[type="text"],
            input[type="url"],
            input[type="password"],
            textarea {
                font-size: 14px;
            }
            button {
                font-size: 14px;
                padding: 10px;
            }
        }
    </style>
    <script>
        // JavaScriptでモーダルの制御や画像のプレビューを行う
        document.addEventListener('DOMContentLoaded', function() {
            // パスワード変更用モーダル
            const changePasswordModal = document.getElementById('changePasswordModal');
            const changePasswordClose = document.getElementById('changePasswordClose');

            if (window.location.hash === '#change_password') {
                changePasswordModal.style.display = 'block';
            }

            changePasswordClose.addEventListener('click', function() {
                changePasswordModal.style.display = 'none';
            });

            window.addEventListener('click', function(event) {
                if (event.target == changePasswordModal) {
                    changePasswordModal.style.display = 'none';
                }
            });

            // リンク編集用モーダル
            const editLinkModal = document.getElementById('editLinkModal');
            const editLinkClose = document.getElementById('editLinkClose');
            const editLinkForm = document.getElementById('editLinkForm');

            document.querySelectorAll('.edit-button').forEach(button => {
                button.addEventListener('click', function() {
                    const linkId = this.dataset.id;
                    // AJAXでリンクデータを取得
                    fetch('get_link.php?id=' + linkId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                document.getElementById('edit_link_id').value = data.link.id;
                                document.getElementById('edit_linkA').value = data.link.linkA;
                                document.getElementById('edit_title').value = data.link.title;
                                document.getElementById('edit_description').value = data.link.description;
                                document.getElementById('edit_twitterSite').value = data.link.twitterSite;
                                document.getElementById('edit_imageAlt').value = data.link.imageAlt;
                                editLinkModal.style.display = 'block';
                            } else {
                                alert(data.message);
                            }
                        });
                });
            });

            editLinkClose.addEventListener('click', function() {
                editLinkModal.style.display = 'none';
            });

            window.addEventListener('click', function(event) {
                if (event.target == editLinkModal) {
                    editLinkModal.style.display = 'none';
                }
            });

            // リンク削除確認
            document.querySelectorAll('.delete-button').forEach(button => {
                button.addEventListener('click', function() {
                    const linkId = this.dataset.id;
                    if (confirm('本当にこのリンクを削除しますか？')) {
                        window.location.href = 'index.php?action=delete_link&id=' + linkId;
                    }
                });
            });

            // リンク生成フォームの画像選択方法のボタン化
            const imageOptionButtons = document.querySelectorAll('.image-option-button');
            const imageOptionInput = document.getElementById('imageOption');
            const imageUrlInput = document.getElementById('imageUrl');
            const imageFileInput = document.getElementById('imageFile');
            const imageUrlLabel = document.getElementById('imageUrlLabel');
            const imageFileLabel = document.getElementById('imageFileLabel');
            const imagePreview = document.getElementById('imagePreview');

            imageOptionButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const option = this.dataset.option;
                    imageOptionInput.value = option;

                    if (option === 'url') {
                        imageUrlLabel.style.display = 'block';
                        imageUrlInput.style.display = 'block';
                        imageFileLabel.style.display = 'none';
                        imageFileInput.style.display = 'none';
                        imagePreview.style.display = 'none';
                    } else if (option === 'upload') {
                        imageUrlLabel.style.display = 'none';
                        imageUrlInput.style.display = 'none';
                        imageFileLabel.style.display = 'block';
                        imageFileInput.style.display = 'block';
                        imagePreview.style.display = 'none';
                    } else if (option === 'template') {
                        // テンプレート選択のポップアップを表示
                        document.getElementById('templateModal').style.display = 'block';
                    }
                });
            });

            // テンプレート選択モーダルの閉じるボタン
            const templateClose = document.getElementById('templateClose');
            templateClose.addEventListener('click', function() {
                document.getElementById('templateModal').style.display = 'none';
            });

            // テンプレート選択時の処理
            document.querySelectorAll('.template-item').forEach(item => {
                item.addEventListener('click', function() {
                    const template = this.dataset.template;
                    imageOptionInput.value = 'template';
                    imagePreview.src = this.querySelector('img').src;
                    imagePreview.style.display = 'block';
                    document.getElementById('templateModal').style.display = 'none';
                });
            });

            // 画像URL入力時のプレビュー表示
            imageUrlInput.addEventListener('blur', function() {
                const url = this.value;
                if (url) {
                    loadImage(url);
                }
            });

            // 画像ファイルアップロード時のプレビュー表示
            imageFileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        loadImage(e.target.result);
                    };
                    reader.readAsDataURL(file);
                }
            });

            // 画像を2:1に切り抜いてプレビュー表示する関数
            function loadImage(src) {
                const img = new Image();
                img.crossOrigin = "Anonymous"; // CORS対策
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    const desiredWidth = img.width;
                    const desiredHeight = img.width / 2; // 2:1のアスペクト比
                    canvas.width = desiredWidth;
                    canvas.height = desiredHeight;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, desiredWidth, desiredHeight);
                    const dataURL = canvas.toDataURL('image/png');
                    imagePreview.src = dataURL;
                    imagePreview.style.display = 'block';
                    // 隠しフィールドに画像データをセット
                    document.getElementById('editedImageData').value = dataURL;
                };
                img.onerror = function() {
                    alert('画像を読み込めませんでした。');
                };
                img.src = src;
            }

            // フォーム送信時に画像データがセットされているか確認
            const generateLinkForm = document.getElementById('generateLinkForm');
            generateLinkForm.addEventListener('submit', function(e) {
                if (imageOptionInput.value !== 'template' && document.getElementById('editedImageData').value === '') {
                    alert('画像を選択してください。');
                    e.preventDefault();
                }
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>サムネイル付きリンク生成サービス</h1>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['action']) && $_GET['action'] === 'change_password'): ?>
            <h2>パスワードの変更</h2>
            <form method="POST" action="index.php?action=change_password">
                <label for="new_password">新しいパスワード</label>
                <input type="password" id="new_password" name="new_password" required>

                <label for="confirm_password">新しいパスワードの確認</label>
                <input type="password" id="confirm_password" name="confirm_password" required>

                <button type="submit" name="change_password">変更</button>
            </form>
        <?php elseif (isset($_GET['action']) && $_GET['action'] === 'dashboard'): ?>
            <h2>ユーザーダッシュボード</h2>
            <form id="generateLinkForm" method="POST" action="index.php?action=generate_link" enctype="multipart/form-data">
                <h3>新規リンクの生成</h3>
                <label for="linkA">遷移先URL（必須）</label>
                <input type="url" id="linkA" name="linkA" required>

                <label for="title">タイトル（必須）</label>
                <input type="text" id="title" name="title" required>

                <label for="description">ページの説明</label>
                <textarea id="description" name="description"></textarea>

                <label for="twitterSite">Twitterアカウント名（@を含む）</label>
                <input type="text" id="twitterSite" name="twitterSite">

                <label for="imageAlt">画像の代替テキスト</label>
                <input type="text" id="imageAlt" name="imageAlt">

                <label>サムネイル画像の選択方法（必須）</label>
                <div class="image-option-buttons">
                    <button type="button" class="image-option-button" data-option="url">画像URLを入力</button>
                    <button type="button" class="image-option-button" data-option="upload">画像ファイルをアップロード</button>
                    <button type="button" class="image-option-button" data-option="template">テンプレートから選択</button>
                </div>
                <input type="hidden" id="imageOption" name="imageOption" required>
                <input type="hidden" id="editedImageData" name="editedImageData">

                <label for="imageUrl" id="imageUrlLabel" style="display:none;">画像URLを入力</label>
                <input type="url" id="imageUrl" name="imageUrl" style="display:none;" placeholder="https://example.com/image.png">

                <label for="imageFile" id="imageFileLabel" style="display:none;">画像ファイルをアップロード</label>
                <input type="file" id="imageFile" name="imageFile" accept="image/*" style="display:none;">

                <img id="imagePreview" class="preview-image" src="#" alt="プレビュー" style="display:none;">

                <button type="submit" name="generate_link">リンクを生成</button>
            </form>

            <h3>生成したリンク一覧</h3>
            <form method="GET" action="index.php">
                <input type="hidden" name="action" value="dashboard">
                <input type="text" name="search" placeholder="検索..." value="<?php echo isset($_GET['search']) ? sanitize($_GET['search']) : ''; ?>">
                <button type="submit">検索</button>
            </form>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>タイトル</th>
                        <th>リンク</th>
                        <th>作成日時</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($user_links)): ?>
                        <?php foreach ($user_links as $link): ?>
                            <tr>
                                <td><?php echo $link['id']; ?></td>
                                <td><?php echo $link['title']; ?></td>
                                <td><a href="<?php echo $link['linkA']; ?>" target="_blank">リンク</a></td>
                                <td><?php echo $link['created_at']; ?></td>
                                <td class="action-buttons">
                                    <button class="edit-button" data-id="<?php echo $link['id']; ?>">編集</button>
                                    <button class="delete-button" data-id="<?php echo $link['id']; ?>">削除</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">生成されたリンクはありません。</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- パスワード変更モーダル -->
    <div id="changePasswordModal" class="modal">
        <div class="modal-content">
            <span class="close" id="changePasswordClose">&times;</span>
            <h2>パスワードの変更</h2>
            <?php if (isset($error) && $_GET['action'] === 'change_password'): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST" action="index.php?action=change_password">
                <label for="new_password">新しいパスワード</label>
                <input type="password" id="new_password" name="new_password" required>

                <label for="confirm_password">新しいパスワードの確認</label>
                <input type="password" id="confirm_password" name="confirm_password" required>

                <button type="submit" name="change_password">変更</button>
            </form>
        </div>
    </div>

    <!-- リンク編集モーダル -->
    <div id="editLinkModal" class="modal">
        <div class="modal-content">
            <span class="close" id="editLinkClose">&times;</span>
            <h2>リンクの編集</h2>
            <form id="editLinkForm" method="POST" action="edit_link.php">
                <input type="hidden" id="edit_link_id" name="link_id">

                <label for="edit_linkA">遷移先URL（必須）</label>
                <input type="url" id="edit_linkA" name="linkA" required>

                <label for="edit_title">タイトル（必須）</label>
                <input type="text" id="edit_title" name="title" required>

                <label for="edit_description">ページの説明</label>
                <textarea id="edit_description" name="description"></textarea>

                <label for="edit_twitterSite">Twitterアカウント名（@を含む）</label>
                <input type="text" id="edit_twitterSite" name="twitterSite">

                <label for="edit_imageAlt">画像の代替テキスト</label>
                <input type="text" id="edit_imageAlt" name="imageAlt">

                <label>サムネイル画像の選択方法</label>
                <div class="image-option-buttons">
                    <button type="button" class="image-option-button" data-option="url">画像URLを入力</button>
                    <button type="button" class="image-option-button" data-option="upload">画像ファイルをアップロード</button>
                    <button type="button" class="image-option-button" data-option="template">テンプレートから選択</button>
                </div>
                <input type="hidden" id="edit_imageOption" name="imageOption">
                <input type="hidden" id="edit_selectedTemplate" name="selectedTemplate">

                <label for="edit_imageUrl" id="edit_imageUrlLabel" style="display:none;">画像URLを入力</label>
                <input type="url" id="edit_imageUrl" name="imageUrl" style="display:none;" placeholder="https://example.com/image.png">

                <label for="edit_imageFile" id="edit_imageFileLabel" style="display:none;">画像ファイルをアップロード</label>
                <input type="file" id="edit_imageFile" name="imageFile" accept="image/*" style="display:none;">

                <img id="edit_imagePreview" class="preview-image" src="#" alt="プレビュー" style="display:none;">

                <button type="submit" name="update_link">変更を保存</button>
            </form>
        </div>
    </div>

    <!-- テンプレート選択モーダル -->
    <div id="templateModal" class="modal">
        <div class="modal-content">
            <span class="close" id="templateClose">&times;</span>
            <h2>テンプレートを選択</h2>
            <div class="template-grid">
                <div class="template-item" data-template="live_now.png">
                    <img src="temp/live_now.png" alt="Live Now">
                </div>
                <div class="template-item" data-template="nude.png">
                    <img src="temp/nude.png" alt="Nude">
                </div>
                <div class="template-item" data-template="gigafile.jpg">
                    <img src="temp/gigafile.jpg" alt="Gigafile">
                </div>
                <div class="template-item" data-template="ComingSoon.png">
                    <img src="temp/ComingSoon.png" alt="Coming Soon">
                </div>
            </div>
        </div>
    </div>
</body>
</html>
