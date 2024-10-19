<?php
session_start();

// CSRFトークン生成
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

// エラーメッセージ初期化
$errors = [];
$success = false;
$generatedLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRFトークンチェック
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
            if ($imageOption === 'url') {
                // 画像URLから取得
                $imageUrl = filter_input(INPUT_POST, 'imageUrl', FILTER_SANITIZE_URL);
                if (empty($imageUrl) || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                    $errors[] = '有効な画像URLを入力してください。';
                } else {
                    $imageData = file_get_contents_curl($imageUrl);
                    if ($imageData === false) {
                        $errors[] = '画像を取得できませんでした。';
                    } else {
                        $imagePath = saveImage($imageData);
                    }
                }
            } elseif ($imageOption === 'upload') {
                // ファイルアップロード処理
                if (isset($_FILES['imageFile']) && $_FILES['imageFile']['error'] === UPLOAD_ERR_OK) {
                    $imageData = file_get_contents($_FILES['imageFile']['tmp_name']);
                    $imagePath = saveImage($imageData);
                } else {
                    $errors[] = '画像ファイルのアップロードに失敗しました。';
                }
            } elseif ($imageOption === 'template') {
                // テンプレート画像を使用
                $templateImages = ['live_now.png', 'nude.png', 'gigafile.jpg', 'ComingSoon.png'];
                if (!in_array($selectedTemplate, $templateImages)) {
                    $errors[] = '有効なテンプレート画像を選択してください。';
                } else {
                    $imagePath = 'temp/' . $selectedTemplate;
                }
            }

            // 画像編集テンプレートの適用
            if (empty($errors) && !empty($editedImageData)) {
                $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $editedImageData));
                $imagePath = saveImage($imageData);
            }

            // 生成されたHTMLファイルの作成
            if (empty($errors)) {
                $uniqueName = uniqid() . '.html';
                $htmlContent = generateHtmlContent($linkA, $title, $description, $twitterSite, $imageAlt, $imagePath);
                file_put_contents($uniqueName, $htmlContent);
                $generatedLink = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $uniqueName;
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

// 画像取得用のcURL関数
function file_get_contents_curl($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
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
    <style>
        /* CSSスタイルをここに記述 */
        body {
            background-color: #121212;
            color: #ffffff;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            text-align: center;
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
            margin-bottom: 10px;
        }
        button {
            background: linear-gradient(to right, #00e5ff, #00b0ff);
            color: #000;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            padding: 10px;
            cursor: pointer;
        }
        button:hover {
            background: linear-gradient(to right, #00b0ff, #00e5ff);
        }
        .error {
            color: #ff5252;
            font-size: 14px;
            animation: shake 0.5s;
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
            margin-top: 10px;
        }
        .success-message input[type="text"] {
            width: 55%;
            margin-top: 10px;
            display: inline-block;
        }
        .success-message button {
            width: 40%;
            margin-left: 5%;
            display: inline-block;
        }
        .preview-image {
            max-width: 400px;
            border-radius: 5px;
            margin-top: 10px;
        }
        /* モーダルスタイル */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            padding-top: 100px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.8);
        }
        .modal-content {
            background-color: #1e1e1e;
            margin: auto;
            padding: 20px;
            border-radius: 10px;
            width: 80%;
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
        }
        .template-item {
            width: 45%;
            margin: 2.5%;
            position: relative;
        }
        .template-item img {
            width: 100%;
            border-radius: 5px;
        }
        .template-item input[type="radio"] {
            position: absolute;
            top: 10px;
            left: 10px;
        }
        /* 詳細設定のスタイル */
        .details-section {
            display: none;
            margin-top: 10px;
        }
    </style>
    <script>
        // JavaScriptをここに記述
        document.addEventListener('DOMContentLoaded', function() {
            // 画像選択方法の切替
            const imageOptions = document.querySelectorAll('input[name="imageOption"]');
            imageOptions.forEach(option => {
                option.addEventListener('change', toggleImageOption);
            });

            function toggleImageOption() {
                document.getElementById('imageUrlInput').style.display = 'none';
                document.getElementById('imageFileInput').style.display = 'none';
                document.getElementById('templateSelection').style.display = 'none';

                if (this.value === 'url') {
                    document.getElementById('imageUrlInput').style.display = 'block';
                } else if (this.value === 'upload') {
                    document.getElementById('imageFileInput').style.display = 'block';
                } else if (this.value === 'template') {
                    document.getElementById('templateSelection').style.display = 'block';
                }
            }

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

        <?php if ($success): ?>
            <div class="success-message">
                <p>リンクが生成されました：</p>
                <input type="text" id="generatedLink" value="<?php echo $generatedLink; ?>" readonly>
                <button id="copyButton">コピー</button>
                <p>保存しない場合、再度登録が必要です。</p>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
            <label>遷移先URL（必須）</label>
            <input type="url" name="linkA" required>

            <label>タイトル（必須）</label>
            <input type="text" name="title" required>

            <label>サムネイル画像の選択方法（必須）</label><br>
            <input type="radio" name="imageOption" value="url" required> 画像URLを入力<br>
            <input type="radio" name="imageOption" value="upload"> 画像ファイルをアップロード<br>
            <input type="radio" name="imageOption" value="template"> テンプレートから選択<br>

            <div id="imageUrlInput" style="display:none;">
                <label>画像URLを入力</label>
                <input type="url" name="imageUrl">
            </div>

            <div id="imageFileInput" style="display:none;">
                <label>画像ファイルをアップロード</label>
                <input type="file" name="imageFile" accept="image/*">
            </div>

            <div id="templateSelection" style="display:none;">
                <label>テンプレートを選択</label>
                <div class="template-grid">
                    <?php
                    $templates = ['live_now.png', 'nude.png', 'gigafile.jpg', 'ComingSoon.png'];
                    foreach ($templates as $template):
                    ?>
                        <div class="template-item">
                            <img src="temp/<?php echo $template; ?>" alt="<?php echo $template; ?>">
                            <input type="radio" name="selectedTemplate" value="<?php echo $template; ?>">
                        </div>
                    <?php endforeach; ?>
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
    </div>
</body>
</html>
