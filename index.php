<?php
// セッションの開始
session_start();

// ユーザー認証のチェック
if (!isset($_SESSION['user_id'])) {
    // クッキーからの認証チェック
    if (isset($_COOKIE['user_id']) && isset($_COOKIE['auth_token'])) {
        $user_id = $_COOKIE['user_id'];
        $auth_token = $_COOKIE['auth_token'];
        // users.jsonからユーザー情報を取得
        $users = json_decode(file_get_contents('users.json'), true);
        if (isset($users[$user_id]) && $users[$user_id]['auth_token'] === $auth_token) {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['is_admin'] = $users[$user_id]['is_admin'];
        }
    }
}

// リダイレクト処理
if (!isset($_SESSION['user_id'])) {
    // ログインフォームの表示
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input_id = htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8');
        $input_password = $_POST['password'];
        
        // users.jsonからユーザー情報を取得
        $users = json_decode(file_get_contents('users.json'), true);
        
        if (isset($users[$input_id])) {
            // パスワードの検証
            if (password_verify($input_password, $users[$input_id]['password'])) {
                $_SESSION['user_id'] = $input_id;
                $_SESSION['is_admin'] = $users[$input_id]['is_admin'];
                
                // クッキーに認証情報を保存（有効期限：30分）
                setcookie('user_id', $input_id, time() + 1800, "/");
                setcookie('auth_token', $users[$input_id]['auth_token'], time() + 1800, "/");
                
                // パスワード変更が必要な場合
                if ($users[$input_id]['password_change_required'] === true) {
                    header('Location: change_password.php');
                    exit();
                }
                
                header('Location: index.php');
                exit();
            }
        }
        $error = "IDまたはパスワードが間違っています。";
    }
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <title>ログイン - サムネイル付きリンク生成サービス</title>
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
            .login-container {
                background-color: #1e1e1e;
                padding: 30px;
                border-radius: 10px;
                width: 90%;
                max-width: 400px;
                box-shadow: 0 0 20px rgba(0,0,0,0.5);
                animation: fadeInUp 1s ease-in-out;
            }
            h2 {
                text-align: center;
                margin-bottom: 20px;
                font-size: 24px;
            }
            label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
            }
            input[type="text"],
            input[type="password"] {
                width: 100%;
                padding: 10px;
                margin-bottom: 15px;
                border: none;
                border-radius: 5px;
                background-color: #2a2a2a;
                color: #ffffff;
                transition: background-color 0.3s;
            }
            input[type="text"]:focus,
            input[type="password"]:focus {
                background-color: #3a3a3a;
                outline: none;
            }
            .login-button {
                width: 100%;
                padding: 10px;
                background: linear-gradient(to right, #00e5ff, #00b0ff);
                border: none;
                border-radius: 5px;
                font-size: 16px;
                cursor: pointer;
                transition: transform 0.2s;
            }
            .login-button:hover {
                background: linear-gradient(to right, #00b0ff, #00e5ff);
                transform: scale(1.02);
            }
            .error {
                color: #ff5252;
                margin-bottom: 15px;
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
        <div class="login-container">
            <h2>ログイン</h2>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST" action="index.php">
                <label for="username">ID</label>
                <input type="text" id="username" name="username" required>
                
                <label for="password">パスワード</label>
                <input type="password" id="password" name="password" required>
                
                <button type="submit" class="login-button">ログイン</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// ユーザーがログインしている場合

$user_id = $_SESSION['user_id'];
$is_admin = $_SESSION['is_admin'];

// パスワード変更ページへのリダイレクト
$users = json_decode(file_get_contents('users.json'), true);
if ($users[$user_id]['password_change_required'] === true && !$is_admin) {
    header('Location: change_password.php');
    exit();
}

// リンク生成および管理の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate_link'])) {
        // リンク生成フォームの処理
        $linkA = filter_var($_POST['linkA'], FILTER_SANITIZE_URL);
        $title = htmlspecialchars($_POST['title'], ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8');
        $twitterSite = htmlspecialchars($_POST['twitterSite'] ?? '', ENT_QUOTES, 'UTF-8');
        $imageAlt = htmlspecialchars($_POST['imageAlt'] ?? '', ENT_QUOTES, 'UTF-8');
        $imageOption = $_POST['imageOption'] ?? '';
        $selectedTemplate = $_POST['selectedTemplate'] ?? '';
        $editedImageData = $_POST['editedImageData'] ?? '';
        
        // バリデーション
        $errors = [];
        if (empty($linkA) || !filter_var($linkA, FILTER_VALIDATE_URL)) {
            $errors[] = '有効な遷移先URLを入力してください。';
        }
        if (empty($title)) {
            $errors[] = 'タイトルを入力してください。';
        }
        if (!in_array($imageOption, ['url', 'upload', 'template'])) {
            $errors[] = 'サムネイル画像の選択方法を選んでください。';
        }
        
        if (empty($errors)) {
            $imagePath = '';
            if ($imageOption === 'template') {
                // テンプレート画像の使用
                $templateImages = ['live_now.png', 'nude.png', 'gigafile.jpg', 'ComingSoon.png'];
                if (!in_array($selectedTemplate, $templateImages)) {
                    $errors[] = '有効なテンプレート画像を選択してください。';
                } else {
                    $imagePath = 'temp/' . $selectedTemplate;
                }
            }
            
            // クライアント側で処理された画像の保存
            if (!empty($editedImageData)) {
                $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $editedImageData));
                $imagePath = saveImage($imageData);
            }
            
            if (empty($imagePath)) {
                $errors[] = '画像の処理に失敗しました。';
            }
            
            if (empty($errors)) {
                // seisei.jsonにリンク情報を保存
                $seisei = json_decode(file_get_contents('seisei.json'), true);
                if (!$seisei) {
                    $seisei = [];
                }
                $link_id = uniqid();
                $seisei[$user_id][$link_id] = [
                    'linkA' => $linkA,
                    'title' => $title,
                    'description' => $description,
                    'twitterSite' => $twitterSite,
                    'imageAlt' => $imageAlt,
                    'imagePath' => $imagePath,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                file_put_contents('seisei.json', json_encode($seisei, JSON_PRETTY_PRINT));
                $success = "リンクが生成されました。";
            }
        }
    }
    
    // リンク編集フォームの処理
    if (isset($_POST['edit_link'])) {
        $link_id = $_POST['link_id'];
        $linkA = filter_var($_POST['linkA'], FILTER_SANITIZE_URL);
        $title = htmlspecialchars($_POST['title'], ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8');
        $twitterSite = htmlspecialchars($_POST['twitterSite'] ?? '', ENT_QUOTES, 'UTF-8');
        $imageAlt = htmlspecialchars($_POST['imageAlt'] ?? '', ENT_QUOTES, 'UTF-8');
        $imageOption = $_POST['imageOption'] ?? '';
        $selectedTemplate = $_POST['selectedTemplate'] ?? '';
        $editedImageData = $_POST['editedImageData'] ?? '';
        
        // バリデーション
        $errors = [];
        if (empty($linkA) || !filter_var($linkA, FILTER_VALIDATE_URL)) {
            $errors[] = '有効な遷移先URLを入力してください。';
        }
        if (empty($title)) {
            $errors[] = 'タイトルを入力してください。';
        }
        if (!in_array($imageOption, ['url', 'upload', 'template'])) {
            $errors[] = 'サムネイル画像の選択方法を選んでください。';
        }
        
        if (empty($errors)) {
            $imagePath = '';
            if ($imageOption === 'template') {
                // テンプレート画像の使用
                $templateImages = ['live_now.png', 'nude.png', 'gigafile.jpg', 'ComingSoon.png'];
                if (!in_array($selectedTemplate, $templateImages)) {
                    $errors[] = '有効なテンプレート画像を選択してください。';
                } else {
                    $imagePath = 'temp/' . $selectedTemplate;
                }
            }
            
            // クライアント側で処理された画像の保存
            if (!empty($editedImageData)) {
                $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $editedImageData));
                $imagePath = saveImage($imageData);
            }
            
            if (empty($imagePath)) {
                $errors[] = '画像の処理に失敗しました。';
            }
            
            if (empty($errors)) {
                // seisei.jsonにリンク情報を更新
                $seisei = json_decode(file_get_contents('seisei.json'), true);
                if (isset($seisei[$user_id][$link_id])) {
                    $seisei[$user_id][$link_id] = [
                        'linkA' => $linkA,
                        'title' => $title,
                        'description' => $description,
                        'twitterSite' => $twitterSite,
                        'imageAlt' => $imageAlt,
                        'imagePath' => $imagePath,
                        'created_at' => $seisei[$user_id][$link_id]['created_at'],
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    file_put_contents('seisei.json', json_encode($seisei, JSON_PRETTY_PRINT));
                    $success = "リンクが更新されました。";
                } else {
                    $errors[] = 'リンクが見つかりませんでした。';
                }
            }
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

// リンク生成コンテンツ生成関数
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
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            animation: fadeIn 1s ease-in-out;
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
            font-size: 28px;
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
            font-size: 16px;
        }
        input[type="text"],
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
        input[type="url"]:focus,
        textarea:focus {
            background-color: #3a3a3a;
            outline: none;
        }
        .generate-button,
        .edit-button {
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
        .generate-button:hover,
        .edit-button:hover {
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
            background-color: #1e1e1e;
            color: #00ff00;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            animation: fadeIn 0.5s;
        }
        .links-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .links-table th,
        .links-table td {
            border: 1px solid #444;
            padding: 10px;
            text-align: left;
        }
        .links-table th {
            background-color: #333;
        }
        .edit-button {
            width: auto;
            padding: 5px 10px;
            font-size: 14px;
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
        @keyframes shake {
            0% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            50% { transform: translateX(5px); }
            75% { transform: translateX(-5px); }
            100% { transform: translateX(0); }
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

            // リンク編集モーダルの処理
            const editModals = document.querySelectorAll('.edit-modal');
            const editCloseButtons = document.querySelectorAll('.edit-close');

            editCloseButtons.forEach(closeBtn => {
                closeBtn.addEventListener('click', function() {
                    this.parentElement.parentElement.style.display = 'none';
                });
            });

            window.addEventListener('click', function(event) {
                editModals.forEach(modal => {
                    if (event.target == modal) {
                        modal.style.display = 'none';
                    }
                });
            });

            // 編集ボタンの処理
            const editButtons = document.querySelectorAll('.edit-button');
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const linkId = this.dataset.linkId;
                    document.getElementById('editModal_' + linkId).style.display = 'block';
                });
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>サムネイル付きリンク生成サービス</h1>
        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- リンク生成フォーム -->
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="imageOption" id="imageOptionInput" required>
            <input type="hidden" name="selectedTemplate" id="selectedTemplateInput">
            <input type="hidden" name="editedImageData" id="editedImageData">

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

            <button type="submit" name="generate_link" class="generate-button">リンクを生成</button>
        </form>

        <!-- ユーザーが生成したリンクの一覧 -->
        <?php
        $seisei = json_decode(file_get_contents('seisei.json'), true);
        if (isset($seisei[$user_id])) {
            echo '<h2>生成したリンク一覧</h2>';
            echo '<table class="links-table">';
            echo '<tr><th>タイトル</th><th>遷移先URL</th><th>作成日時</th><th>操作</th></tr>';
            foreach ($seisei[$user_id] as $link_id => $link) {
                echo '<tr>';
                echo '<td>' . $link['title'] . '</td>';
                echo '<td><a href="' . htmlspecialchars($link['linkA'], ENT_QUOTES, 'UTF-8') . '" target="_blank">' . htmlspecialchars($link['linkA'], ENT_QUOTES, 'UTF-8') . '</a></td>';
                echo '<td>' . $link['created_at'] . '</td>';
                echo '<td><button class="edit-button" data-link-id="' . $link_id . '">編集</button></td>';
                echo '</tr>';
                
                // 編集モーダル
                echo '<div id="editModal_' . $link_id . '" class="modal edit-modal">';
                echo '<div class="modal-content">';
                echo '<span class="close edit-close">&times;</span>';
                echo '<h2>リンク編集</h2>';
                echo '<form method="POST" enctype="multipart/form-data">';
                echo '<input type="hidden" name="link_id" value="' . $link_id . '">';
                echo '<label>遷移先URL（必須）</label>';
                echo '<input type="url" name="linkA" value="' . htmlspecialchars($link['linkA'], ENT_QUOTES, 'UTF-8') . '" required>';
                echo '<label>タイトル（必須）</label>';
                echo '<input type="text" name="title" value="' . htmlspecialchars($link['title'], ENT_QUOTES, 'UTF-8') . '" required>';
                echo '<label>ページの説明</label>';
                echo '<textarea name="description">' . htmlspecialchars($link['description'], ENT_QUOTES, 'UTF-8') . '</textarea>';
                echo '<label>Twitterアカウント名（@を含む）</label>';
                echo '<input type="text" name="twitterSite" value="' . htmlspecialchars($link['twitterSite'], ENT_QUOTES, 'UTF-8') . '">';
                echo '<label>画像の代替テキスト</label>';
                echo '<input type="text" name="imageAlt" value="' . htmlspecialchars($link['imageAlt'], ENT_QUOTES, 'UTF-8') . '">';
                echo '<label>サムネイル画像の選択方法（必須）</label>';
                echo '<div class="image-option-buttons">';
                echo '<button type="button" class="image-option-button" data-option="url">画像URLを入力</button>';
                echo '<button type="button" class="image-option-button" data-option="upload">画像ファイルをアップロード</button>';
                echo '<button type="button" class="image-option-button" data-option="template">テンプレートから選択</button>';
                echo '</div>';
                echo '<div id="edit_imageUrlInput_' . $link_id . '" style="display:none;">';
                echo '<label>画像URLを入力</label>';
                echo '<input type="url" name="imageUrl">';
                echo '</div>';
                echo '<div id="edit_imageFileInput_' . $link_id . '" style="display:none;">';
                echo '<label>画像ファイルをアップロード</label>';
                echo '<input type="file" name="imageFile" accept="image/*">';
                echo '</div>';
                echo '<button type="submit" name="edit_link" class="edit-button">更新</button>';
                echo '</form>';
                echo '</div>';
                echo '</div>';
            }
            echo '</table>';
        }
        ?>

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

    </div>
</body>
</html>
