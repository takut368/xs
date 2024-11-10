<?php
session_start();

// 認証チェック
if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit();
}

$username = $_SESSION['username'];
$isAdmin = ($username === 'admin');

// 一般ユーザーのみアクセス可能
if ($isAdmin) {
    header('Location: admin/index.php');
    exit();
}

$errors = [];
$success = false;

// パスワード変更強制チェック
$users = json_decode(file_get_contents('data/users.json'), true);
if ($users[$username]['force_reset']) {
    header('Location: reset_password.php');
    exit();
}

// リンク編集処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_link'])) {
    $linkId = $_POST['link_id'] ?? '';
    $newLinkA = filter_input(INPUT_POST, 'linkA', FILTER_SANITIZE_URL);
    $newTitle = htmlspecialchars($_POST['title'], ENT_QUOTES, 'UTF-8');
    $newDescription = htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8');
    $newTwitterSite = htmlspecialchars($_POST['twitterSite'] ?? '', ENT_QUOTES, 'UTF-8');
    $newImageAlt = htmlspecialchars($_POST['imageAlt'] ?? '', ENT_QUOTES, 'UTF-8');
    $imageOption = $_POST['imageOption'] ?? '';
    $selectedTemplate = $_POST['selectedTemplate'] ?? '';
    $editedImageData = $_POST['editedImageData'] ?? '';

    // バリデーション
    if (empty($linkId)) {
        $errors[] = 'リンクIDが指定されていません。';
    }
    if (empty($newLinkA) || !filter_var($newLinkA, FILTER_VALIDATE_URL)) {
        $errors[] = '有効な遷移先URLを入力してください。';
    }
    if (empty($newTitle)) {
        $errors[] = 'タイトルを入力してください。';
    }
    if (!in_array($imageOption, ['url', 'upload', 'template'])) {
        $errors[] = 'サムネイル画像の選択方法を選んでください。';
    }

    // リンクの存在確認
    $seisei = json_decode(file_get_contents('data/seisei.json'), true);
    if (!isset($seisei[$linkId]) || $seisei[$linkId]['user'] !== $username) {
        $errors[] = '指定されたリンクが存在しないか、アクセス権限がありません。';
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

        // リンクの更新
        if (empty($errors)) {
            $seisei[$linkId] = [
                'user' => $username,
                'linkA' => $newLinkA,
                'title' => $newTitle,
                'description' => $newDescription,
                'twitterSite' => $newTwitterSite,
                'imageAlt' => $newImageAlt,
                'imagePath' => $imagePath,
                'created_at' => $seisei[$linkId]['created_at']
            ];
            saveData('data/seisei.json', $seisei);

            // リンクの内容を更新するためにindex.phpを更新
            $dirPath = $linkId;
            $filePath = $dirPath . '/index.php';
            $htmlContent = generateHtmlContent($newLinkA, $newTitle, $newDescription, $newTwitterSite, $imageAlt, $imagePath, $newLinkA);
            file_put_contents($filePath, $htmlContent);

            $success = 'リンクが更新されました。';
            logAction('リンク編集: ' . $linkId, $username);
        }
    }
}

// リンクIDの取得
$linkId = $_GET['id'] ?? '';

$seisei = json_decode(file_get_contents('data/seisei.json'), true);
$link = null;
if (!empty($linkId) && isset($seisei[$linkId]) && $seisei[$linkId]['user'] === $username) {
    $link = $seisei[$linkId];
} else {
    header('Location: index.php');
    exit();
}

// リンク編集フォームのプリフィル
$prefillLinkA = htmlspecialchars($link['linkA'], ENT_QUOTES, 'UTF-8');
$prefillTitle = htmlspecialchars($link['title'], ENT_QUOTES, 'UTF-8');
$prefillDescription = htmlspecialchars($link['description'], ENT_QUOTES, 'UTF-8');
$prefillTwitterSite = htmlspecialchars($link['twitterSite'], ENT_QUOTES, 'UTF-8');
$prefillImageAlt = htmlspecialchars($link['imageAlt'], ENT_QUOTES, 'UTF-8');
$prefillImagePath = htmlspecialchars($link['imagePath'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>リンク編集 - サムネイル付きリンク生成サービス</title>
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
        .section {
            background-color: #2a2a2a;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            animation: slideIn 0.5s;
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
            background-color: #3a3a3a;
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
            background-color: #4a4a4a;
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
            width: 100%;
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
        .success-message {
            background-color: #3a3a3a;
            color: #ffffff;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            animation: fadeInUp 0.5s;
            text-align: center;
        }
        .success-message p {
            margin-bottom: 10px;
        }
        /* テンプレート選択モーダル */
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
            background-color: #2a2a2a;
            margin: 50px auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
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

            // セッションタイムアウト（30分）
            setInterval(function() {
                const now = new Date().getTime();
                const timeout = 30 * 60 * 1000; // 30分
                if (sessionStorage.getItem('lastActivity')) {
                    const lastActivity = parseInt(sessionStorage.getItem('lastActivity'));
                    if (now - lastActivity > timeout) {
                        alert('セッションがタイムアウトしました。再度ログインしてください。');
                        window.location.href = 'logout.php';
                    }
                }
                sessionStorage.setItem('lastActivity', now);
            }, 5 * 60 * 1000); // チェックは5分ごと
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>リンク編集</h1>
        <div class="section">
            <?php if ($success): ?>
                <div class="success-message">
                    <p><?php echo htmlspecialchars($success); ?></p>
                    <a href="index.php" style="color: #00e5ff;">リンク一覧へ戻る</a>
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="edit_link" value="1">
                    <input type="hidden" name="link_id" value="<?php echo htmlspecialchars($linkId); ?>">

                    <label for="linkA">遷移先URL（必須）</label>
                    <input type="url" id="linkA" name="linkA" value="<?php echo $prefillLinkA; ?>" required>

                    <label for="title">タイトル（必須）</label>
                    <input type="text" id="title" name="title" value="<?php echo $prefillTitle; ?>" required>

                    <label>サムネイル画像の選択方法（必須）</label>
                    <div class="image-option-buttons">
                        <button type="button" class="image-option-button" data-option="url">画像URLを入力</button>
                        <button type="button" class="image-option-button" data-option="upload">画像ファイルをアップロード</button>
                        <button type="button" class="image-option-button" data-option="template">テンプレートから選択</button>
                    </div>

                    <div id="imageUrlInput" style="display:none;">
                        <label for="imageUrl">画像URLを入力</label>
                        <input type="url" id="imageUrl" name="imageUrl">
                    </div>

                    <div id="imageFileInput" style="display:none;">
                        <label for="imageFile">画像ファイルをアップロード</label>
                        <input type="file" id="imageFile" name="imageFile" accept="image/*">
                    </div>

                    <div class="details-section">
                        <label for="description">ページの説明</label>
                        <textarea id="description" name="description"><?php echo $prefillDescription; ?></textarea>

                        <label for="twitterSite">Twitterアカウント名（@を含む）</label>
                        <input type="text" id="twitterSite" name="twitterSite" value="<?php echo $prefillTwitterSite; ?>">

                        <label for="imageAlt">画像の代替テキスト</label>
                        <input type="text" id="imageAlt" name="imageAlt" value="<?php echo $prefillImageAlt; ?>">
                    </div>

                    <button type="button" id="toggleDetails">詳細設定</button>
                    <button type="submit">リンクを更新</button>
                </form>

                <?php if (!empty($errors)): ?>
                    <div class="error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
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
</body>
</html>
