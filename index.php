<?php
// セッションの開始
session_start();

// 自動作成するディレクトリとファイルのパス
$baseDir = __DIR__;
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
$generatedLink = '';

// ログアウト処理
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // ログアウトログの記録
    if (isset($_SESSION['user'])) {
        $log = [
            "user" => $_SESSION['user'],
            "action" => "logout",
            "timestamp" => date("Y-m-d H:i:s")
        ];
        $logs = json_decode(file_get_contents($logsFile), true);
        $logs[] = $log;
        file_put_contents($logsFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    session_unset();
    session_destroy();
    // クッキーの削除
    setcookie("rememberme", "", time() - 3600, "/");
    header("Location: index.php");
    exit();
}

// ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    if (isset($users[$username])) {
        if (password_verify($password, $users[$username]['password'])) {
            // 認証成功
            $_SESSION['user'] = $username;
            // クッキーに保存（30日間）
            if (isset($_POST['rememberme'])) {
                setcookie("rememberme", $username, time() + (30 * 24 * 60 * 60), "/");
            }
            // ログインログの記録
            $log = [
                "user" => $username,
                "action" => "login",
                "timestamp" => date("Y-m-d H:i:s")
            ];
            $logs = json_decode(file_get_contents($logsFile), true);
            $logs[] = $log;
            file_put_contents($logsFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // 初回ログイン時のパスワード変更強制
            if (isset($users[$username]['force_password_change']) && $users[$username]['force_password_change'] === true) {
                header("Location: change_password.php");
                exit();
            }

            // 管理者の場合は管理者ページにリダイレクト
            if ($users[$username]['is_admin']) {
                header("Location: admin/index.php");
                exit();
            }
        } else {
            $errors[] = "ユーザー名またはパスワードが正しくありません。";
            // 不正ログイン試行の記録
            $log = [
                "user" => $username,
                "action" => "failed_login",
                "timestamp" => date("Y-m-d H:i:s")
            ];
            $logs = json_decode(file_get_contents($logsFile), true);
            $logs[] = $log;
            file_put_contents($logsFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    } else {
        $errors[] = "ユーザー名またはパスワードが正しくありません。";
        // 不正ログイン試行の記録
        $log = [
            "user" => $username,
            "action" => "failed_login",
            "timestamp" => date("Y-m-d H:i:s")
        ];
        $logs = json_decode(file_get_contents($logsFile), true);
        $logs[] = $log;
        file_put_contents($logsFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

// クッキーからの自動ログイン処理
if (!isset($_SESSION['user']) && isset($_COOKIE['rememberme'])) {
    $username = $_COOKIE['rememberme'];
    if (isset($users[$username])) {
        $_SESSION['user'] = $username;
        // ログインログの記録
        $log = [
            "user" => $username,
            "action" => "login_via_cookie",
            "timestamp" => date("Y-m-d H:i:s")
        ];
        $logs = json_decode(file_get_contents($logsFile), true);
        $logs[] = $log;
        file_put_contents($logsFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 初回ログイン時のパスワード変更強制
        if (isset($users[$username]['force_password_change']) && $users[$username]['force_password_change'] === true) {
            header("Location: change_password.php");
            exit();
        }

        // 管理者の場合は管理者ページにリダイレクト
        if ($users[$username]['is_admin']) {
            header("Location: admin/index.php");
            exit();
        }
    }
}

// ユーザーがログインしている場合のみ以下のコードを実行
if (isset($_SESSION['user']) && !$users[$_SESSION['user']]['is_admin']) {
    $currentUser = $_SESSION['user'];
    // ユーザーのリンクデータの取得
    $seiseiData = json_decode(file_get_contents($seiseiFile), true);
    if (!isset($seiseiData[$currentUser])) {
        $seiseiData[$currentUser] = [];
    }

    // リンク生成処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
        $linkA = filter_var($_POST['linkA'], FILTER_SANITIZE_URL);
        $title = htmlspecialchars(trim($_POST['title']), ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars(trim($_POST['description']), ENT_QUOTES, 'UTF-8');
        $twitterSite = htmlspecialchars(trim($_POST['twitterSite']), ENT_QUOTES, 'UTF-8');
        $imageAlt = htmlspecialchars(trim($_POST['imageAlt']), ENT_QUOTES, 'UTF-8');
        $imageOption = $_POST['imageOption'];
        $selectedTemplate = $_POST['selectedTemplate'] ?? '';

        // バリデーション
        if (empty($linkA) || !filter_var($linkA, FILTER_VALIDATE_URL)) {
            $errors[] = "有効な遷移先URLを入力してください。";
        }
        if (empty($title)) {
            $errors[] = "タイトルを入力してください。";
        }
        if (!in_array($imageOption, ['url', 'upload', 'template'])) {
            $errors[] = "サムネイル画像の選択方法を選んでください。";
        }

        // 画像処理
        $imagePath = '';
        if ($imageOption === 'url') {
            $imageUrl = filter_var($_POST['imageUrl'], FILTER_SANITIZE_URL);
            if (empty($imageUrl) || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $errors[] = "有効な画像URLを入力してください。";
            } else {
                $imageData = file_get_contents_curl($imageUrl);
                if ($imageData === false) {
                    $errors[] = "画像を取得できませんでした。";
                } else {
                    $imagePath = saveImage($imageData, $uploadsDir);
                }
            }
        } elseif ($imageOption === 'upload') {
            if (isset($_FILES['imageFile']) && $_FILES['imageFile']['error'] === UPLOAD_ERR_OK) {
                // ファイルタイプの検証
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['imageFile']['tmp_name']);
                finfo_close($finfo);
                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($mime, $allowedMimes)) {
                    $errors[] = "許可されていないファイルタイプです。";
                } else {
                    $imageData = file_get_contents($_FILES['imageFile']['tmp_name']);
                    $imagePath = saveImage($imageData, $uploadsDir);
                }
            } else {
                $errors[] = "画像ファイルのアップロードに失敗しました。";
            }
        } elseif ($imageOption === 'template') {
            $templateImages = ['live_now.png', 'nude.png', 'gigafile.jpg', 'ComingSoon.png'];
            if (!in_array($selectedTemplate, $templateImages)) {
                $errors[] = "有効なテンプレート画像を選択してください。";
            } else {
                $imagePath = $tempDir . '/' . $selectedTemplate;
            }
        }

        // 画像編集データの保存
        if (!empty($_POST['editedImageData'])) {
            $editedImageData = $_POST['editedImageData'];
            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $editedImageData));
            $imagePath = saveImage($imageData, $uploadsDir);
        }

        if (empty($imagePath)) {
            $errors[] = "画像の処理に失敗しました。";
        }

        // リンク生成
        if (empty($errors)) {
            $uniqueId = uniqid();
            $dirPath = $baseDir . '/' . $uniqueId;
            if (!mkdir($dirPath, 0777, true)) {
                $errors[] = "リンク生成用のディレクトリの作成に失敗しました。";
            } else {
                $linkFolder = $uniqueId;
                $linkPath = $linkFolder . '/index.php';
                $linkUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $linkFolder;

                // リダイレクト用のindex.phpを生成
                $redirectContent = generateRedirectPage($linkA, $title, $description, $twitterSite, $imageAlt, $imagePath);
                file_put_contents($dirPath . '/index.php', $redirectContent);

                // seisei.jsonにリンク情報を追加
                $seiseiData[$currentUser][$uniqueId] = [
                    "linkA" => $linkA,
                    "title" => $title,
                    "description" => $description,
                    "twitterSite" => $twitterSite,
                    "imageAlt" => $imageAlt,
                    "imagePath" => $imagePath,
                    "created_at" => date("Y-m-d H:i:s")
                ];
                file_put_contents($seiseiFile, json_encode($seiseiData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                $generatedLink = $linkUrl;
                $success = true;
            }
        }
    }

    // 画像保存関数
    function saveImage($imageData, $uploadsDir)
    {
        // アップロードファイルのサイズ制限（5MB）
        if (strlen($imageData) > 5 * 1024 * 1024) {
            return '';
        }
        // ユニークなファイル名の生成
        $imageName = uniqid() . '.png';
        $imagePath = $uploadsDir . '/' . $imageName;
        if (file_put_contents($imagePath, $imageData)) {
            return 'uploads/' . $imageName;
        } else {
            return '';
        }
    }

    // 画像取得用のcURL関数
    function file_get_contents_curl($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // SSL証明書の検証を有効化（セキュリティ上推奨）
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    // リダイレクトページ生成関数
    function generateRedirectPage($linkA, $title, $description, $twitterSite, $imageAlt, $imagePath)
    {
        $baseUrl = 'https://' . $_SERVER['HTTP_HOST'];
        $imageUrl = $baseUrl . '/' . $imagePath;
        $metaDescription = !empty($description) ? $description : $title;
        $twitterSiteTag = !empty($twitterSite) ? '<meta name="twitter:site" content="' . htmlspecialchars($twitterSite, ENT_QUOTES, 'UTF-8') . '">' : '';
        $imageAltTag = !empty($imageAlt) ? htmlspecialchars($imageAlt, ENT_QUOTES, 'UTF-8') : htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        $html = '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>
    <meta name="description" content="' . htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') . '">
    <meta http-equiv="refresh" content="0; URL=' . htmlspecialchars($linkA, ENT_QUOTES, 'UTF-8') . '">
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    ' . $twitterSiteTag . '
    <meta name="twitter:title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">
    <meta name="twitter:description" content="' . htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') . '">
    <meta name="twitter:image" content="' . $imageUrl . '">
    <meta name="twitter:image:alt" content="' . $imageAltTag . '">
</head>
<body>
    <p>リダイレクト中...</p>
</body>
</html>';

        return $html;
    }

// HTMLコンテンツ
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>サムネイル付きリンク生成サービス - ログイン</title>
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
            min-height: 100vh;
            padding: 20px;
            overflow: hidden;
        }
        .container {
            width: 100%;
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
        /* クッキー保存用 */
        .rememberme {
            margin-top: 10px;
            display: flex;
            align-items: center;
        }
        .rememberme input {
            margin-right: 5px;
        }
        /* リンク生成フォーム */
        .link-form {
            display: none;
            margin-top: 20px;
        }
        /* リンク一覧 */
        .link-list {
            margin-top: 20px;
        }
        .link-item {
            background-color: #2a2a2a;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.3s;
        }
        .link-item:hover {
            background-color: #3a3a3a;
        }
        .link-actions button {
            margin-left: 5px;
            padding: 5px 10px;
            font-size: 14px;
            cursor: pointer;
            border: none;
            border-radius: 3px;
        }
        .link-actions button.view {
            background-color: #4caf50;
            color: #fff;
        }
        .link-actions button.edit {
            background-color: #2196f3;
            color: #fff;
        }
        .link-actions button.delete {
            background-color: #f44336;
            color: #fff;
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
            .container {
                height: 100vh;
                overflow-y: auto;
            }
            .image-option-button {
                flex: 1 1 100%;
            }
            .link-actions button {
                margin-left: 0;
                margin-top: 5px;
            }
        }
    </style>
    <script>
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
                if (detailsSection.style.display === 'none' || detailsSection.style.display === '') {
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
        <h1>サムネイル付きリンク生成サービス</h1>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message">
                <p>リンクが生成されました：</p>
                <input type="text" id="generatedLink" value="<?php echo htmlspecialchars($generatedLink, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                <button id="copyButton">コピー</button>
                <p>保存しない場合、再度登録が必要です。</p>
            </div>
        <?php endif; ?>

        <?php if (!isset($_SESSION['user'])): ?>
            <!-- ログインフォーム -->
            <form method="POST">
                <label>ユーザー名</label>
                <input type="text" name="username" required>

                <label>パスワード</label>
                <input type="password" name="password" required>

                <div class="rememberme">
                    <input type="checkbox" name="rememberme" id="rememberme">
                    <label for="rememberme">ログイン状態を保持する</label>
                </div>

                <button type="submit" name="login">ログイン</button>
            </form>
        <?php else: ?>
            <!-- リンク生成フォーム -->
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
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
                                    <img src="<?php echo htmlspecialchars($tempDir . '/' . $template, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($template, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="radio" name="templateRadio" value="<?php echo htmlspecialchars($template, ENT_QUOTES, 'UTF-8'); ?>">
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

                <button type="submit" name="generate">リンクを生成</button>
            </form>

            <!-- ユーザーのリンク一覧 -->
            <div class="link-list">
                <h2>生成したリンク一覧</h2>
                <?php if (!empty($seiseiData[$currentUser])): ?>
                    <?php foreach ($seiseiData[$currentUser] as $id => $link): ?>
                        <div class="link-item">
                            <div>
                                <a href="<?php echo htmlspecialchars('https://' . $_SERVER['HTTP_HOST'] . '/' . $id, ENT_QUOTES, 'UTF-8'); ?>" target="_blank"><?php echo htmlspecialchars($link['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                            </div>
                            <div class="link-actions">
                                <button class="view" onclick="window.open('<?php echo htmlspecialchars('https://' . $_SERVER['HTTP_HOST'] . '/' . $id, ENT_QUOTES, 'UTF-8'); ?>', '_blank')">表示</button>
                                <button class="edit" onclick="window.location.href='edit_link.php?id=<?php echo urlencode($id); ?>'">編集</button>
                                <button class="delete" onclick="if(confirm('本当に削除しますか？')){ window.location.href='delete_link.php?id=<?php echo urlencode($id); ?>' }">削除</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>まだリンクが生成されていません。</p>
                <?php endif; ?>
            </div>

            <!-- ログアウトボタン -->
            <a href="index.php?action=logout"><button type="button" style="background: #ff5252;">ログアウト</button></a>
        <?php endif; ?>
    </div>
</body>
</html>
