<?php
// セッションの開始は一番最初に行う必要があります
session_start();

// ユーザー情報を格納するJSONファイル
$usersFile = 'users.json';
$seiseiFile = 'seisei.json';

// 管理者IDとパスワード
define('ADMIN_ID', 'admin');
define('ADMIN_PASSWORD', 'admin');

// クッキーの有効期限（例: 1時間）
$cookieExpire = time() + 3600;

// 初期化
$errors = [];
$success = '';
$generatedLink = '';

// アクセス制御: ドメイン末尾にidが指定されていない場合、ログインページ以外は真っ白にする
if (!isset($_GET['id'])) {
    // ログインしているか確認
    if (!isset($_SESSION['user_id']) && !isset($_COOKIE['user_id'])) {
        // ログインページを表示
        // 以下にログインページのコードが続きます
    } else {
        // ログイン済み
        // 以下にログイン後のページ（リンク生成等）のコードが続きます
    }
} else {
    // idが指定されている場合、リンクリダイレクト処理を行う
    $linkId = $_GET['id'];
    if (file_exists($linkId . '/index.php')) {
        include($linkId . '/index.php');
    } else {
        // 存在しないリンクIDの場合、真っ白なページを表示
        exit();
    }
    exit();
}

// ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $inputId = $_POST['login_id'] ?? '';
    $inputPassword = $_POST['login_password'] ?? '';

    // 管理者ログイン
    if ($inputId === ADMIN_ID && $inputPassword === ADMIN_PASSWORD) {
        $_SESSION['user_id'] = ADMIN_ID;
        setcookie('user_id', ADMIN_ID, $cookieExpire, "/");
        header('Location: admin/index.php');
        exit();
    }

    // 一般ユーザーログイン
    if (file_exists($usersFile)) {
        $usersData = json_decode(file_get_contents($usersFile), true);
        if (isset($usersData[$inputId])) {
            if ($usersData[$inputId]['password'] === $inputPassword) {
                $_SESSION['user_id'] = $inputId;
                setcookie('user_id', $inputId, $cookieExpire, "/");
                // 初回ログインチェック
                if ($usersData[$inputId]['force_change'] === true) {
                    $_SESSION['force_change'] = true;
                    header('Location: index.php');
                    exit();
                }
                $userId = $inputId;
                $isAdmin = false;
            } else {
                $errors[] = 'パスワードが間違っています。';
            }
        } else {
            $errors[] = 'ユーザーIDが存在しません。';
        }
    } else {
        $errors[] = 'ユーザーデータが存在しません。';
    }
}

// パスワード変更処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_credentials') {
    if (isset($_SESSION['user_id']) && isset($_SESSION['force_change'])) {
        $newId = htmlspecialchars($_POST['new_id'], ENT_QUOTES, 'UTF-8');
        $newPassword = htmlspecialchars($_POST['new_password'], ENT_QUOTES, 'UTF-8');
        $confirmPassword = htmlspecialchars($_POST['confirm_password'], ENT_QUOTES, 'UTF-8');

        if (empty($newId) || empty($newPassword) || empty($confirmPassword)) {
            $errors[] = 'IDとパスワードをすべて入力してください。';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'パスワードが一致しません。';
        } elseif (file_exists($usersFile) && isset($usersData[$newId])) {
            $errors[] = 'このIDは既に使用されています。';
        } else {
            // ユーザー情報を更新
            if (file_exists($usersFile)) {
                $usersData = json_decode(file_get_contents($usersFile), true);
            } else {
                $usersData = [];
            }

            // 現在のユーザーIDを取得
            $currentId = $_SESSION['user_id'];

            // 新しいIDが変更されている場合
            if ($newId !== $currentId) {
                // ユーザー情報を新しいIDに移行
                $usersData[$newId] = [
                    'password' => $newPassword,
                    'force_change' => false
                ];
                unset($usersData[$currentId]);

                // seisei.jsonのデータも更新
                if (file_exists($seiseiFile)) {
                    $seiseiData = json_decode(file_get_contents($seiseiFile), true);
                    if (isset($seiseiData[$currentId])) {
                        $seiseiData[$newId] = $seiseiData[$currentId];
                        unset($seiseiData[$currentId]);
                        file_put_contents($seiseiFile, json_encode($seiseiData, JSON_PRETTY_PRINT));
                    }
                }

                // セッションとクッキーを更新
                $_SESSION['user_id'] = $newId;
                setcookie('user_id', $newId, $cookieExpire, "/");
            } else {
                // パスワードのみ更新
                $usersData[$currentId]['password'] = $newPassword;
                $usersData[$currentId]['force_change'] = false;
            }

            file_put_contents($usersFile, json_encode($usersData, JSON_PRETTY_PRINT));
            unset($_SESSION['force_change']);
            $success = 'IDとパスワードが変更されました。';
        }
    } else {
        $errors[] = '変更する権限がありません。';
    }
}

// ログアウト処理
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    setcookie('user_id', '', time() - 3600, "/");
    header('Location: index.php');
    exit();
}

// ユーザーがログインしているか確認
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $isAdmin = ($userId === ADMIN_ID);
} elseif (isset($_COOKIE['user_id'])) {
    $userId = $_COOKIE['user_id'];
    $_SESSION['user_id'] = $userId;
    $isAdmin = ($userId === ADMIN_ID);
} else {
    $userId = '';
    $isAdmin = false;
}

// ユーザーがログインしており、adminではない場合にリンク生成機能を提供
if (!empty($userId) && !$isAdmin) {
    // リンク生成処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_link') {
        // フォームデータの取得とサニタイズ
        $linkA = filter_input(INPUT_POST, 'linkA', FILTER_SANITIZE_URL);
        $title = htmlspecialchars($_POST['title'], ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8');
        $twitterSite = htmlspecialchars($_POST['twitterSite'] ?? '', ENT_QUOTES, 'UTF-8');
        $imageAlt = htmlspecialchars($_POST['imageAlt'] ?? '', ENT_QUOTES, 'UTF-8');
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
                    $imagePath = 'temp/' . $selectedTemplate;
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

            // リンクの生成
            if (empty($errors)) {
                $uniqueId = uniqid();
                $dirPath = $uniqueId;
                if (!mkdir($dirPath, 0777, true)) {
                    $errors[] = 'ディレクトリの作成に失敗しました。';
                } else {
                    $filePath = $dirPath . '/index.php';
                    $htmlContent = generateHtmlContent($linkA, $title, $description, $twitterSite, $imageAlt, $imagePath);
                    file_put_contents($filePath, $htmlContent);
                    $generatedLink = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $uniqueId;

                    // seisei.jsonに記録
                    if (!file_exists($seiseiFile)) {
                        file_put_contents($seiseiFile, json_encode([], JSON_PRETTY_PRINT));
                    }
                    $seiseiData = json_decode(file_get_contents($seiseiFile), true);
                    if (!isset($seiseiData[$userId])) {
                        $seiseiData[$userId] = [];
                    }
                    $seiseiData[$userId][] = [
                        'id' => $uniqueId,
                        'link' => $generatedLink,
                        'redirect_url' => $linkA,
                        'title' => $title,
                        'description' => $description,
                        'twitter_site' => $twitterSite,
                        'image_alt' => $imageAlt,
                        'image_path' => $imagePath,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    file_put_contents($seiseiFile, json_encode($seiseiData, JSON_PRETTY_PRINT));

                    $success = 'リンクが生成されました。';
                }
            }
        }
    }

    // リンク編集処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_link') {
        $linkId = $_POST['link_id'] ?? '';
        $newRedirectUrl = filter_input(INPUT_POST, 'new_redirect_url', FILTER_SANITIZE_URL);
        $newTitle = htmlspecialchars($_POST['new_title'], ENT_QUOTES, 'UTF-8');
        $newDescription = htmlspecialchars($_POST['new_description'] ?? '', ENT_QUOTES, 'UTF-8');
        $newTwitterSite = htmlspecialchars($_POST['new_twitterSite'] ?? '', ENT_QUOTES, 'UTF-8');
        $newImageAlt = htmlspecialchars($_POST['new_imageAlt'] ?? '', ENT_QUOTES, 'UTF-8');
        $newImageOption = $_POST['new_imageOption'] ?? '';
        $newSelectedTemplate = $_POST['new_selectedTemplate'] ?? '';
        $newEditedImageData = $_POST['new_editedImageData'] ?? '';

        // バリデーション
        if (empty($newRedirectUrl) || !filter_var($newRedirectUrl, FILTER_VALIDATE_URL)) {
            $errors[] = '有効な遷移先URLを入力してください。';
        }
        if (empty($newTitle)) {
            $errors[] = 'タイトルを入力してください。';
        }
        if (!in_array($newImageOption, ['url', 'upload', 'template'])) {
            $errors[] = 'サムネイル画像の選択方法を選んでください。';
        }

        // リンクデータの取得
        if (file_exists($seiseiFile)) {
            $seiseiData = json_decode(file_get_contents($seiseiFile), true);
            if (isset($seiseiData[$userId])) {
                foreach ($seiseiData[$userId] as &$link) {
                    if ($link['id'] === $linkId) {
                        // サムネイル画像の処理
                        if ($newImageOption === 'template') {
                            $templateImages = ['live_now.png', 'nude.png', 'gigafile.jpg', 'ComingSoon.png'];
                            if (!in_array($newSelectedTemplate, $templateImages)) {
                                $errors[] = '有効なテンプレート画像を選択してください。';
                            } else {
                                $link['image_path'] = 'temp/' . $newSelectedTemplate;
                            }
                        }

                        if (!empty($newEditedImageData)) {
                            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $newEditedImageData));
                            $link['image_path'] = saveImage($imageData);
                        }

                        // データの更新
                        $link['redirect_url'] = $newRedirectUrl;
                        $link['title'] = $newTitle;
                        $link['description'] = $newDescription;
                        $link['twitter_site'] = $newTwitterSite;
                        $link['image_alt'] = $newImageAlt;
                        $link['updated_at'] = date('Y-m-d H:i:s');
                        break;
                    }
                }
                unset($link);
                file_put_contents($seiseiFile, json_encode($seiseiData, JSON_PRETTY_PRINT));
                $success = 'リンクが更新されました。';
            } else {
                $errors[] = 'リンクが見つかりません。';
            }
        } else {
            $errors[] = 'リンクデータが存在しません。';
        }
    }
}

// 画像保存関数
function saveImage($imageData)
{
    $imageName = uniqid() . '.png';
    $imagePath = 'uploads/' . $imageName;
    file_put_contents($imagePath, $imageData);
    return $imagePath;
}

// HTMLコンテンツ生成関数
function generateHtmlContent($linkA, $title, $description, $twitterSite, $imageAlt, $imagePath)
{
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
            max-width: 600px;
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
            /* スタイルを適用 */
            background: #ff5252;
            color: #ffffff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .success-message button:hover {
            background: #ff1744;
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

            // 初回ログイン時のIDとパスワード変更を強制
            <?php if (isset($_SESSION['force_change']) && $_SESSION['force_change'] === true && !isset($_GET['id'])): ?>
                // 自動的にパスワード変更セクションを表示
                window.onload = function() {
                    document.getElementById('changeCredentialsSection').style.display = 'block';
                };
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

        <?php if (isset($_SESSION['user_id']) && !$isAdmin): ?>
            <!-- ユーザーダッシュボード -->
            <!-- IDとパスワードの変更フォーム（初回ログイン時のみ表示） -->
            <?php if (isset($_SESSION['force_change']) && $_SESSION['force_change'] === true): ?>
                <div id="changeCredentialsSection" style="display:none;">
                    <h2>IDとパスワードの変更</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_credentials">
                        <label>新しいID</label>
                        <input type="text" name="new_id" required>

                        <label>新しいパスワード</label>
                        <input type="password" name="new_password" required>

                        <label>新しいパスワード（確認）</label>
                        <input type="password" name="confirm_password" required>

                        <button type="submit">変更</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- リンク生成フォーム -->
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
            <?php
            if (file_exists($seiseiFile)) {
                $seiseiData = json_decode(file_get_contents($seiseiFile), true);
                if (isset($seiseiData[$userId]) && count($seiseiData[$userId]) > 0) {
                    echo '<table border="1" cellpadding="10" cellspacing="0" style="width:100%; margin-top:10px;">';
                    echo '<tr><th>ID</th><th>リンク</th><th>タイトル</th><th>操作</th></tr>';
                    foreach ($seiseiData[$userId] as $link) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($link['id'], ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '<td><a href="' . htmlspecialchars($link['link'], ENT_QUOTES, 'UTF-8') . '" target="_blank">' . htmlspecialchars($link['link'], ENT_QUOTES, 'UTF-8') . '</a></td>';
                        echo '<td>' . htmlspecialchars($link['title'], ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '<td><button onclick="editLink(\'' . htmlspecialchars($link['id'], ENT_QUOTES, 'UTF-8') . '\')">編集</button></td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<p>生成されたリンクはありません。</p>';
                }
            }
            ?>

            <!-- リンク編集モーダル -->
            <div id="editModal" class="modal">
                <div class="modal-content">
                    <span class="close" id="editClose">&times;</span>
                    <h2>リンクを編集</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_link">
                        <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                        <input type="hidden" id="edit_link_id" name="link_id">

                        <label>遷移先URL（必須）</label>
                        <input type="url" name="new_redirect_url" required>

                        <label>タイトル（必須）</label>
                        <input type="text" name="new_title" required>

                        <label>サムネイル画像の選択方法（必須）</label>
                        <div class="image-option-buttons">
                            <button type="button" class="image-option-button" data-option="url">画像URLを入力</button>
                            <button type="button" class="image-option-button" data-option="upload">画像ファイルをアップロード</button>
                            <button type="button" class="image-option-button" data-option="template">テンプレートから選択</button>
                        </div>

                        <div id="edit_imageUrlInput" style="display:none;">
                            <label>画像URLを入力</label>
                            <input type="url" name="new_imageUrl">
                        </div>

                        <div id="edit_imageFileInput" style="display:none;">
                            <label>画像ファイルをアップロード</label>
                            <input type="file" name="new_imageFile" accept="image/*">
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
                                            <input type="radio" name="new_templateRadio" value="<?php echo $template; ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <label>ページの説明</label>
                        <textarea name="new_description"></textarea>

                        <label>Twitterアカウント名（@を含む）</label>
                        <input type="text" name="new_twitterSite">

                        <label>画像の代替テキスト</label>
                        <input type="text" name="new_imageAlt">

                        <button type="submit">更新</button>
                    </form>
                </div>
            </div>

            <script>
                // リンク編集モーダルの処理
                const editModal = document.getElementById('editModal');
                const editClose = document.getElementById('editClose');
                const editTemplateModal = document.getElementById('editTemplateModal');
                const editTemplateClose = document.getElementById('editTemplateClose');

                function editLink(linkId) {
                    document.getElementById('edit_link_id').value = linkId;
                    editModal.style.display = 'block';
                }

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

                const editTemplateItems = editTemplateModal.querySelectorAll('.template-item');
                editTemplateItems.forEach(item => {
                    item.addEventListener('click', function() {
                        const radio = this.querySelector('input[type="radio"]');
                        radio.checked = true;
                        editTemplateModal.style.display = 'none';
                        // プレビュー表示
                        let preview = document.getElementById('edit_imagePreview');
                        if (!preview) {
                            preview = document.createElement('img');
                            preview.id = 'edit_imagePreview';
                            preview.classList.add('preview-image');
                            editModal.querySelector('.modal-content').appendChild(preview);
                        }
                        preview.src = this.querySelector('img').src;
                        // サーバーに送信するselectedTemplateの値を設定
                        document.getElementById('new_selectedTemplate').value = radio.value;
                    });
                });

                // 編集リンクの画像選択ボタン処理
                const editImageOptionButtons = editModal.querySelectorAll('.image-option-button');
                editImageOptionButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        // クラスの切り替え
                        editImageOptionButtons.forEach(btn => btn.classList.remove('active'));
                        this.classList.add('active');

                        const selectedOption = this.dataset.option;
                        document.getElementById('edit_imageOptionInput').value = selectedOption;

                        // 各オプションの表示・非表示
                        document.getElementById('edit_imageUrlInput').style.display = 'none';
                        document.getElementById('edit_imageFileInput').style.display = 'none';

                        if (selectedOption === 'url') {
                            document.getElementById('edit_imageUrlInput').style.display = 'block';
                        } else if (selectedOption === 'upload') {
                            document.getElementById('edit_imageFileInput').style.display = 'block';
                        } else if (selectedOption === 'template') {
                            // テンプレート選択モーダルを表示
                            openEditTemplateModal();
                        }
                    });
                });

                function openEditTemplateModal() {
                    editTemplateModal.style.display = 'block';
                }
            </script>

            <!-- ユーザーのリンク一覧 -->
            <?php
            if (file_exists($seiseiFile)) {
                $seiseiData = json_decode(file_get_contents($seiseiFile), true);
                if (isset($seiseiData[$userId]) && count($seiseiData[$userId]) > 0) {
                    echo '<table border="1" cellpadding="10" cellspacing="0" style="width:100%; margin-top:10px;">';
                    echo '<tr><th>ID</th><th>リンク</th><th>タイトル</th><th>操作</th></tr>';
                    foreach ($seiseiData[$userId] as $link) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($link['id'], ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '<td><a href="' . htmlspecialchars($link['link'], ENT_QUOTES, 'UTF-8') . '" target="_blank">' . htmlspecialchars($link['link'], ENT_QUOTES, 'UTF-8') . '</a></td>';
                        echo '<td>' . htmlspecialchars($link['title'], ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '<td><button onclick="editLink(\'' . htmlspecialchars($link['id'], ENT_QUOTES, 'UTF-8') . '\')">編集</button></td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<p>生成されたリンクはありません。</p>';
                }
            }
            ?>

            <!-- ログアウトリンク -->
            <p style="margin-top:20px;"><a href="index.php?action=logout" style="color:#00e5ff; text-decoration: none;"><button style="width:auto; padding: 10px 20px;">ログアウト</button></a></p>

        <?php elseif (isset($_SESSION['user_id']) && isset($_SESSION['force_change']) && !$isAdmin): ?>
            <!-- パスワード変更フォーム -->
            <div id="changeCredentialsSection" style="display:none;">
                <h2>IDとパスワードの変更</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="change_credentials">
                    <label>新しいID</label>
                    <input type="text" name="new_id" required>

                    <label>新しいパスワード</label>
                    <input type="password" name="new_password" required>

                    <label>新しいパスワード（確認）</label>
                    <input type="password" name="confirm_password" required>

                    <button type="submit">変更</button>
                </form>
            </div>
        <?php else: ?>
            <!-- ログインフォーム -->
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <label>ID</label>
                <input type="text" name="login_id" required>

                <label>パスワード</label>
                <input type="password" name="login_password" required>

                <button type="submit">ログイン</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
