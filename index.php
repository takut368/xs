<?php
session_start();

// アクセスにidパラメータがない場合は空白ページを表示
if (!isset($_GET['id'])) {
    exit;
}

// パスワード変更モード
$changePassword = false;

// エラーメッセージと成功メッセージの初期化
$errors = [];
$success = false;
$generatedLink = '';

// ユーザー情報とリンクデータのファイルパス
$usersFile = 'users.json';
$seiseiFile = 'seisei.json';

// CSRFトークンの生成
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

// ユーザがログアウトをリクエストした場合
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php?id=' . $_GET['id']);
    exit;
}

// ユーザーがログインしている場合
if (isset($_SESSION['user'])) {
    // ユーザーが初回ログインでパスワード変更が必要な場合
    if ($_SESSION['user']['needsPasswordChange']) {
        $changePassword = true;
    } else {
        // ユーザーインターフェースの処理
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generateLink'])) {
            // CSRFトークンの検証
            if (!hash_equals($_SESSION['token'], $_POST['token'])) {
                $errors[] = '不正なリクエストです。';
            } else {
                // 入力データの取得とサニタイズ
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
    
                    // 生成されたHTMLファイルの作成
                    if (empty($errors)) {
                        // ユニークなフォルダを作成し、その中にindex.phpを生成
                        $uniqueDir = uniqid();
                        $dirPath = $uniqueDir;
                        if (!mkdir($dirPath, 0777, true)) {
                            $errors[] = 'ディレクトリの作成に失敗しました。';
                        } else {
                            $filePath = $dirPath . '/index.php';
                            $htmlContent = generateHtmlContent($linkA, $title, $description, $twitterSite, $imageAlt, $imagePath);
                            file_put_contents($filePath, $htmlContent);
                            $generatedLink = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $uniqueDir;
    
                            // seisei.jsonにリンク情報を保存
                            $seiseiData = json_decode(file_get_contents($seiseiFile), true);
                            if (!isset($seiseiData[$_SESSION['user']['id']])) {
                                $seiseiData[$_SESSION['user']['id']] = [];
                            }
                            $seiseiData[$_SESSION['user']['id']][] = [
                                'linkID' => $uniqueDir,
                                'title' => $title,
                                'linkA' => $linkA,
                                'description' => $description,
                                'twitterSite' => $twitterSite,
                                'imageAlt' => $imageAlt,
                                'imagePath' => $imagePath
                            ];
                            file_put_contents($seiseiFile, json_encode($seiseiData, JSON_PRETTY_PRINT));
    
                            $success = true;
                        }
                    }
                }
            }
        }
    
        // ユーザーがリンクを編集した場合
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editLink'])) {
            // CSRFトークンの検証
            if (!hash_equals($_SESSION['token'], $_POST['token'])) {
                $errors[] = '不正なリクエストです。';
            } else {
                // 編集対象のリンクID
                $linkID = htmlspecialchars($_POST['linkID'], ENT_QUOTES, 'UTF-8');
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
    
                    // seisei.jsonの更新
                    if (empty($errors)) {
                        $seiseiData = json_decode(file_get_contents($seiseiFile), true);
                        foreach ($seiseiData[$_SESSION['user']['id']] as &$link) {
                            if ($link['linkID'] === $linkID) {
                                $link['linkA'] = $linkA;
                                $link['title'] = $title;
                                $link['description'] = $description;
                                $link['twitterSite'] = $twitterSite;
                                $link['imageAlt'] = $imageAlt;
                                $link['imagePath'] = $imagePath;
                                break;
                            }
                        }
                        file_put_contents($seiseiFile, json_encode($seiseiData, JSON_PRETTY_PRINT));
                        $success = true;
                    }
                }
            }
        }
    
    } else {
        // ユーザーがログインしていない場合
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
            // CSRFトークンの検証
            if (!hash_equals($_SESSION['token'], $_POST['token'])) {
                $errors[] = '不正なリクエストです。';
            } else {
                // 入力データの取得とサニタイズ
                $inputID = htmlspecialchars($_POST['id'], ENT_QUOTES, 'UTF-8');
                $inputPassword = htmlspecialchars($_POST['password'], ENT_QUOTES, 'UTF-8');
    
                // 管理者の認証
                if ($inputID === 'admin' && $inputPassword === 'admin') {
                    $_SESSION['admin'] = true;
                    header('Location: admin/index.php');
                    exit;
                }
    
                // ユーザーの認証
                $usersData = json_decode(file_get_contents($usersFile), true);
                foreach ($usersData as $user) {
                    if ($user['id'] === $inputID && $user['password'] === $inputPassword) {
                        $_SESSION['user'] = $user;
                        header('Location: index.php?id=' . $_GET['id']);
                        exit;
                    }
                }
                $errors[] = 'IDまたはパスワードが間違っています。';
            }
        }
    
        // ユーザーがパスワード変更をリクエストした場合
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['changePassword'])) {
            // CSRFトークンの検証
            if (!hash_equals($_SESSION['token'], $_POST['token'])) {
                $errors[] = '不正なリクエストです。';
            } else {
                // 入力データの取得とサニタイズ
                $newID = htmlspecialchars($_POST['newID'], ENT_QUOTES, 'UTF-8');
                $newPassword = htmlspecialchars($_POST['newPassword'], ENT_QUOTES, 'UTF-8');
    
                // バリデーション
                if (empty($newID) || empty($newPassword)) {
                    $errors[] = '新しいIDとパスワードを入力してください。';
                }
    
                // IDの重複チェック
                $usersData = json_decode(file_get_contents($usersFile), true);
                foreach ($usersData as $user) {
                    if ($user['id'] === $newID) {
                        $errors[] = 'このIDは既に使用されています。';
                        break;
                    }
                }
    
                if (empty($errors)) {
                    // ユーザー情報の更新
                    foreach ($usersData as &$user) {
                        if ($user['id'] === $_SESSION['user']['id']) {
                            $user['id'] = $newID;
                            $user['password'] = $newPassword;
                            $user['needsPasswordChange'] = false;
                            break;
                        }
                    }
                    file_put_contents($usersFile, json_encode($usersData, JSON_PRETTY_PRINT));
    
                    // セッション情報の更新
                    $_SESSION['user']['id'] = $newID;
                    $_SESSION['user']['password'] = $newPassword;
                    $_SESSION['user']['needsPasswordChange'] = false;
    
                    $success = true;
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
    
    // HTMLコンテンツ生成関数
    function generateHtmlContent($linkA, $title, $description, $twitterSite, $imageAlt, $imagePath)
    {
        $imageUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $imagePath;
        $metaDescription = !empty($description) ? $description : $title;
        $twitterSiteTag = !empty($twitterSite) ? '<meta name="twitter:site" content="' . $twitterSite . '">' : '';
        $imageAltTag = !empty($imageAlt) ? $imageAlt : $title;
    
        $html = '<?php
    header("Location: ' . htmlspecialchars($linkA, ENT_QUOTES, 'UTF-8') . '");
    exit;
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <title>' . $title . '</title>
        <meta name="description" content="' . $metaDescription . '">
        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image">
        ' . $twitterSiteTag . '
        <meta name="twitter:title" content="' . $title . '">
        <meta name="twitter:description" content="' . $metaDescription . '">
        <meta name="twitter:image" content="' . $imageUrl . '">
        <meta name="twitter:image:alt" content="' . $imageAltTag . '">
        <meta http-equiv="refresh" content="0; URL=' . htmlspecialchars($linkA, ENT_QUOTES, 'UTF-8') . '">
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
            h1 {
                text-align: center;
                margin-bottom: 20px;
            }
            form {
                display: flex;
                flex-direction: column;
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
                transition: transform 0.2s, background 0.3s;
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
                gap: 10px;
            }
            .image-option-button {
                background: linear-gradient(to right, #00e5ff, #00b0ff);
                color: #000;
                border: none;
                border-radius: 5px;
                font-size: 14px;
                padding: 10px;
                cursor: pointer;
                flex: 1;
                text-align: center;
                transition: transform 0.2s, background 0.3s;
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
                .image-option-buttons {
                    flex-direction: column;
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
                        detailsButton.textContent = '詳細設定を閉じる';
                    } else {
                        detailsSection.style.display = 'none';
                        detailsButton.textContent = '詳細設定を開く';
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
                }
    
                // パスワード変更フォームの表示
                const changePasswordForm = document.getElementById('changePasswordForm');
                if (changePasswordForm) {
                    // ボタンを押すとフォームを表示
                    // この処理は後でPHPから出力するため、ここでは不要
                }
            });
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
    
            <?php if ($success && !isset($_SESSION['admin'])): ?>
                <div class="success-message">
                    <p>リンクが生成されました：</p>
                    <input type="text" id="generatedLink" value="<?php echo $generatedLink; ?>" readonly>
                    <button id="copyButton">コピー</button>
                    <p>保存しない場合、再度登録が必要です。</p>
                </div>
            <?php endif; ?>
    
            <?php if (isset($_SESSION['user'])): ?>
                <?php if ($changePassword): ?>
                    <!-- パスワード変更フォーム -->
                    <div class="success-message">
                        <p>初回ログインのため、IDとパスワードを変更してください。</p>
                        <form method="POST">
                            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                            <label>新しいID</label>
                            <input type="text" name="newID" required>
    
                            <label>新しいパスワード</label>
                            <input type="password" name="newPassword" required>
    
                            <button type="submit" name="changePassword">変更</button>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- ユーザーインターフェース -->
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                        <input type="hidden" id="imageOptionInput" name="imageOption" required>
                        <input type="hidden" id="selectedTemplateInput" name="selectedTemplate">
                        <input type="hidden" id="editedImageData" name="editedImageData">
    
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
    
                        <button type="submit" name="generateLink">リンクを生成</button>
                    </form>
    
                    <!-- 生成済みリンクの一覧 -->
                    <h2 style="margin-top:40px; text-align:center;">生成済みリンク一覧</h2>
                    <?php
                    $seiseiData = json_decode(file_get_contents($seiseiFile), true);
                    if (isset($seiseiData[$_SESSION['user']['id']])):
                        foreach ($seiseiData[$_SESSION['user']['id']] as $link):
                    ?>
                            <div class="success-message">
                                <p><strong>タイトル:</strong> <?php echo htmlspecialchars($link['title']); ?></p>
                                <p><strong>遷移先URL:</strong> <a href="<?php echo htmlspecialchars($link['linkA']); ?>" target="_blank"><?php echo htmlspecialchars($link['linkA']); ?></a></p>
                                <p><strong>リンク:</strong> <a href="<?php echo htmlspecialchars($link['linkID']); ?>" target="_blank">https://<?php echo $_SERVER['HTTP_HOST'] . '/' . htmlspecialchars($link['linkID']); ?></a></p>
                                <form method="POST">
                                    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                                    <input type="hidden" name="linkID" value="<?php echo htmlspecialchars($link['linkID']); ?>">
                                    <input type="hidden" name="linkA" value="<?php echo htmlspecialchars($link['linkA']); ?>">
                                    <input type="hidden" name="title" value="<?php echo htmlspecialchars($link['title']); ?>">
                                    <input type="hidden" name="description" value="<?php echo htmlspecialchars($link['description']); ?>">
                                    <input type="hidden" name="twitterSite" value="<?php echo htmlspecialchars($link['twitterSite']); ?>">
                                    <input type="hidden" name="imageAlt" value="<?php echo htmlspecialchars($link['imageAlt']); ?>">
                                    <input type="hidden" name="imageOption" value="upload"> <!-- Default -->
                                    <input type="hidden" name="selectedTemplate" value="<?php echo htmlspecialchars($link['selectedTemplate'] ?? ''); ?>">
                                    <input type="hidden" name="editedImageData" value="<?php echo htmlspecialchars($link['editedImageData'] ?? ''); ?>">
                                    <button type="submit" name="editLink">編集</button>
                                </form>
                            </div>
                    <?php
                        endforeach;
                    else:
                        echo '<p style="text-align:center;">生成されたリンクはありません。</p>';
                    endif;
                    ?>
    
                <?php endif; ?>
            <?php else: ?>
                <?php if ($changePassword): ?>
                    <!-- パスワード変更フォーム（ログインしていない場合は不要） -->
                <?php else: ?>
                    <!-- ログインフォーム -->
                    <form method="POST">
                        <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                        <label>ID</label>
                        <input type="text" name="id" required>
    
                        <label>パスワード</label>
                        <input type="password" name="password" required>
    
                        <button type="submit" name="login">ログイン</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </body>
    </html>
    ```

## 5. `admin/index.php`

以下が`admin/index.php`の完全なソースコードです。このファイルは、管理者が新規ユーザーを作成し、ユーザーごとのリンク生成数を閲覧できるようにします。また、各ユーザーの生成したリンク内容を閲覧できます。

```php
<?php
session_start();

// ユーザー情報とリンクデータのファイルパス
$usersFile = '../users.json';
$seiseiFile = '../seisei.json';

// 管理者がログインしているか確認
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: ../index.php?id=' . $_GET['id']);
    exit;
}

// エラーメッセージと成功メッセージの初期化
$errors = [];
$success = false;

// ユーザーの作成処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['createUser'])) {
    // CSRFトークンの検証
    if (!hash_equals($_SESSION['token'], $_POST['token'])) {
        $errors[] = '不正なリクエストです。';
    } else {
        // 入力データの取得とサニタイズ
        $newID = htmlspecialchars($_POST['newID'], ENT_QUOTES, 'UTF-8');
        $newPassword = htmlspecialchars($_POST['newPassword'], ENT_QUOTES, 'UTF-8');
    
        // バリデーション
        if (empty($newID) || empty($newPassword)) {
            $errors[] = '新しいIDとパスワードを入力してください。';
        }
    
        // IDの重複チェック
        $usersData = json_decode(file_get_contents($usersFile), true);
        foreach ($usersData as $user) {
            if ($user['id'] === $newID) {
                $errors[] = 'このIDは既に使用されています。';
                break;
            }
        }
    
        if (empty($errors)) {
            // 新規ユーザーの追加
            $usersData[] = [
                'id' => $newID,
                'password' => $newPassword,
                'needsPasswordChange' => true
            ];
            file_put_contents($usersFile, json_encode($usersData, JSON_PRETTY_PRINT));
            $success = true;
        }
    }
}

// ユーザーのリンクデータの取得
$seiseiData = json_decode(file_get_contents($seiseiFile), true);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>管理者ページ - サムネイル付きリンク生成サービス</title>
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
        h1, h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        form {
            display: flex;
            flex-direction: column;
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
            transition: transform 0.2s, background 0.3s;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            animation: fadeIn 0.5s;
        }
        th, td {
            padding: 10px;
            border: 1px solid #333;
            text-align: left;
        }
        th {
            background-color: #2a2a2a;
        }
        tr:hover {
            background-color: #3a3a3a;
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
        /* メディアクエリ */
        @media screen and (max-width: 600px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }
            th {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            tr {
                margin-bottom: 10px;
            }
            td {
                border: none;
                position: relative;
                padding-left: 50%;
            }
            td::before {
                content: attr(data-label);
                position: absolute;
                left: 10px;
                font-weight: bold;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>管理者ページ</h1>
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
    
        <!-- ユーザー作成フォーム -->
        <h2>新規ユーザー作成</h2>
        <form method="POST">
            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
            <label>新しいユーザーID</label>
            <input type="text" name="newID" required>
    
            <label>新しいユーザーのパスワード</label>
            <input type="password" name="newPassword" required>
    
            <button type="submit" name="createUser">ユーザーを作成</button>
        </form>
    
        <!-- ユーザーごとのリンク生成数の表示 -->
        <h2>ユーザーのリンク生成数</h2>
        <?php
        $usersData = json_decode(file_get_contents($usersFile), true);
        if (!empty($usersData)):
        ?>
            <table>
                <thead>
                    <tr>
                        <th>ユーザーID</th>
                        <th>リンク生成数</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usersData as $user): ?>
                        <tr>
                            <td><a href="#" class="viewLinks" data-user="<?php echo htmlspecialchars($user['id']); ?>"><?php echo htmlspecialchars($user['id']); ?></a></td>
                            <td><?php echo isset($seiseiData[$user['id']]) ? count($seiseiData[$user['id']]) : 0; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
    
            <!-- ユーザーのリンク内容を表示するモーダル -->
            <div id="userLinksModal" class="modal">
                <div class="modal-content">
                    <span class="close" id="userLinksClose">&times;</span>
                    <h2 id="modalUserID">ユーザーID: </h2>
                    <table id="userLinksTable">
                        <thead>
                            <tr>
                                <th>タイトル</th>
                                <th>遷移先URL</th>
                                <th>リンクURL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- リンク内容がここに挿入されます -->
                        </tbody>
                    </table>
                </div>
            </div>
    
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const viewLinks = document.querySelectorAll('.viewLinks');
                    const userLinksModal = document.getElementById('userLinksModal');
                    const userLinksClose = document.getElementById('userLinksClose');
                    const modalUserID = document.getElementById('modalUserID');
                    const userLinksTableBody = document.querySelector('#userLinksTable tbody');
    
                    viewLinks.forEach(link => {
                        link.addEventListener('click', function(e) {
                            e.preventDefault();
                            const userID = this.dataset.user;
                            modalUserID.textContent = 'ユーザーID: ' + userID;
                            fetch('get_user_links.php?user=' + encodeURIComponent(userID))
                                .then(response => response.json())
                                .then(data => {
                                    userLinksTableBody.innerHTML = '';
                                    if (data.links.length > 0) {
                                        data.links.forEach(link => {
                                            const row = document.createElement('tr');
    
                                            const titleCell = document.createElement('td');
                                            titleCell.textContent = link.title;
                                            row.appendChild(titleCell);
    
                                            const urlCell = document.createElement('td');
                                            const urlLink = document.createElement('a');
                                            urlLink.href = link.linkA;
                                            urlLink.textContent = link.linkA;
                                            urlLink.target = '_blank';
                                            urlCell.appendChild(urlLink);
                                            row.appendChild(urlCell);
    
                                            const linkURLCell = document.createElement('td');
                                            const linkURLLink = document.createElement('a');
                                            linkURLLink.href = link.linkID;
                                            linkURLLink.textContent = 'https://' + window.location.hostname + '/' + link.linkID;
                                            linkURLLink.target = '_blank';
                                            linkURLCell.appendChild(linkURLLink);
                                            row.appendChild(linkURLCell);
    
                                            userLinksTableBody.appendChild(row);
                                        });
                                    } else {
                                        const row = document.createElement('tr');
                                        const cell = document.createElement('td');
                                        cell.colSpan = 3;
                                        cell.textContent = 'リンクが生成されていません。';
                                        cell.style.textAlign = 'center';
                                        row.appendChild(cell);
                                        userLinksTableBody.appendChild(row);
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                });
    
                            userLinksModal.style.display = 'block';
                        });
                    });
    
                    userLinksClose.addEventListener('click', function() {
                        userLinksModal.style.display = 'none';
                    });
    
                    window.addEventListener('click', function(event) {
                        if (event.target == userLinksModal) {
                            userLinksModal.style.display = 'none';
                        }
                    });
                });
            </script>
        <?php else: ?>
            <p style="text-align:center;">ユーザーが存在しません。</p>
        <?php endif; ?>
    </div>
</body>
</html>
