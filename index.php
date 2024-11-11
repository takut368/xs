<?php
// セッションの開始
session_start();

// ディレクトリとファイルの自動作成
$directories = ['uploads', 'temp', 'admin', 'data'];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// 必要なJSONファイルの初期化
$data_files = [
    'data/users.json' => json_encode([]),
    'data/seisei.json' => json_encode([]),
    'data/logs.json' => json_encode([]),
];
foreach ($data_files as $file => $content) {
    if (!file_exists($file)) {
        file_put_contents($file, $content);
    }
}

// CSRFトークン生成
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

// エラーメッセージと成功メッセージの初期化
$errors = [];
$success = false;
$generatedLink = '';

// ユーザーがログインしているかどうかを確認
if (isset($_SESSION['user'])) {
    $currentUser = $_SESSION['user'];
} elseif (isset($_COOKIE['rememberme'])) {
    // クッキーからユーザーを復元
    $rememberToken = $_COOKIE['rememberme'];
    $users = json_decode(file_get_contents('data/users.json'), true);
    foreach ($users as $user) {
        if (isset($user['remember_token']) && hash_equals($user['remember_token'], $rememberToken)) {
            $_SESSION['user'] = $user['id'];
            $currentUser = $user['id'];
            break;
        }
    }
}

// POSTリクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ログインフォームからの送信
    if (isset($_POST['login'])) {
        // CSRFトークンの検証
        if (!hash_equals($_SESSION['token'], $_POST['token'])) {
            $errors[] = '不正なリクエストです。';
        } else {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $remember = isset($_POST['remember']);

            if ($username === 'admin' && $password === 'admin') {
                // 管理者ログイン
                $_SESSION['admin'] = true;
                header('Location: admin/index.php');
                exit();
            } else {
                // ユーザーログイン
                $users = json_decode(file_get_contents('data/users.json'), true);
                $userFound = false;
                foreach ($users as &$user) {
                    if ($user['id'] === $username && password_verify($password, $user['password'])) {
                        $_SESSION['user'] = $username;
                        $currentUser = $username;
                        $userFound = true;

                        // 「Remember Me」機能の実装
                        if ($remember) {
                            $token = bin2hex(random_bytes(16));
                            $user['remember_token'] = $token;
                            setcookie('rememberme', $token, time() + (86400 * 30), "/"); // 30日間有効
                        }

                        break;
                    }
                }
                file_put_contents('data/users.json', json_encode($users, JSON_PRETTY_PRINT));

                if (!$userFound) {
                    $errors[] = '無効なIDまたはパスワードです。';
                }
            }
        }
    }

    // リンク生成フォームからの送信
    if (isset($_POST['generate_link'])) {
        if (!isset($_SESSION['user'])) {
            $errors[] = 'ログインが必要です。';
        } else {
            // CSRFトークンの検証
            if (!hash_equals($_SESSION['token'], $_POST['token'])) {
                $errors[] = '不正なリクエストです。';
            } else {
                // 入力データの取得とサニタイズ
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

                    // 生成されたフォルダとindex.phpの作成
                    if (empty($errors)) {
                        $uniqueDir = uniqid();
                        $dirPath = $uniqueDir;
                        if (!mkdir($dirPath, 0755, true)) {
                            $errors[] = 'ディレクトリの作成に失敗しました。';
                        } else {
                            $filePath = $dirPath . '/index.php';
                            $htmlContent = generateHtmlContent($linkA, $title, $description, $twitterSite, $imageAlt, $imagePath);
                            file_put_contents($filePath, $htmlContent);
                            $generatedLink = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $uniqueDir;
                            $success = true;

                            // seisei.jsonへのリンク情報の保存
                            $seisei = json_decode(file_get_contents('data/seisei.json'), true);
                            if (!isset($seisei[$currentUser])) {
                                $seisei[$currentUser] = [];
                            }
                            $seisei[$currentUser][$uniqueDir] = [
                                'link' => $linkA,
                                'title' => $title,
                                'description' => $description,
                                'twitterSite' => $twitterSite,
                                'imageAlt' => $imageAlt,
                                'imagePath' => $imagePath,
                                'created_at' => date('Y-m-d H:i:s'),
                            ];
                            file_put_contents('data/seisei.json', json_encode($seisei, JSON_PRETTY_PRINT));

                            // ログの記録
                            logAction($currentUser, 'link_generated', $uniqueDir);
                        }
                    }
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

// HTMLコンテンツ生成関数
function generateHtmlContent($linkA, $title, $description, $twitterSite, $imageAlt, $imagePath)
{
    $imageUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $imagePath;
    $metaDescription = !empty($description) ? $description : $title;
    $twitterSiteTag = !empty($twitterSite) ? '<meta name="twitter:site" content="' . $twitterSite . '">' : '';
    $imageAltTag = !empty($imageAlt) ? $imageAlt : $title;

    $html = '<?php
    header("Location: ' . htmlspecialchars($linkA, ENT_QUOTES, 'UTF-8') . '");
    exit();
    ?>';
    
    // Alternatively, if you want to use meta refresh
    /*
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
    */

    return $html;
}

// ログ記録関数
function logAction($user, $action, $detail = '')
{
    $logs = json_decode(file_get_contents('data/logs.json'), true);
    $logs[] = [
        'user' => $user,
        'action' => $action,
        'detail' => $detail,
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
    ];
    file_put_contents('data/logs.json', json_encode($logs, JSON_PRETTY_PRINT));
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
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
            font-size: 16px;
        }
        input[type="text"],
        input[type="url"],
        textarea,
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
        input[type="url"]:focus,
        textarea:focus,
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
            transition: transform 0.2s;
        }
        .image-option-button:hover {
            background: linear-gradient(to right, #00b0ff, #00e5ff);
            transform: scale(1.02);
        }
        .image-option-button.active {
            background: #00e5ff;
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
            .image-option-buttons {
                gap: 5px;
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

            // 初回アクセス時にid=123がない場合は画面を真っ白にする
            <?php if (!isset($_SESSION['user'])): ?>
                if (!window.location.search.includes('id=123')) {
                    document.body.innerHTML = '';
                }
            <?php endif; ?>
        });
    </script>
</head>
<body>
    <div class="container">
        <?php if (!isset($_SESSION['user'])): ?>
            <h1>ログイン</h1>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token'], ENT_QUOTES, 'UTF-8'); ?>">
                <label>ユーザーID</label>
                <input type="text" name="username" required>
                <label>パスワード</label>
                <input type="password" name="password" required>
                <label>
                    <input type="checkbox" name="remember"> Remember Me
                </label>
                <button type="submit" name="login">ログイン</button>
            </form>
        <?php else: ?>
            <h1>サムネイル付きリンク生成</h1>
            <?php if ($success): ?>
                <div class="success-message">
                    <p>リンクが生成されました：</p>
                    <input type="text" id="generatedLink" value="<?php echo htmlspecialchars($generatedLink, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    <button id="copyButton">コピー</button>
                    <p>保存しない場合、再度登録が必要です。</p>
                </div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token'], ENT_QUOTES, 'UTF-8'); ?>">
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

                <button type="button" id="toggleDetails">詳細設定</button>
                <div id="detailsSection" class="details-section">
                    <label>ページの説明</label>
                    <textarea name="description"></textarea>

                    <label>Twitterアカウント名（@を含む）</label>
                    <input type="text" name="twitterSite">

                    <label>画像の代替テキスト</label>
                    <input type="text" name="imageAlt">
                </div>

                <button type="submit" name="generate_link">リンクを生成</button>
            </form>
        <?php endif; ?>
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
                        <img src="temp/<?php echo htmlspecialchars($template, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($template, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="radio" name="templateRadio" value="<?php echo htmlspecialchars($template, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
