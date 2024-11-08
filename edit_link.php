<?php
// セッションの開始
session_start();

// 自動作成するディレクトリとファイルのパス
$baseDir = __DIR__;
$dataDir = $baseDir . '/data';
$seiseiFile = $dataDir . '/seisei.json';
$logsFile = $dataDir . '/logs.json';

// ユーザーがログインしているか確認
if (!isset($_SESSION['user']) || $_SESSION['user'] === 'admin') {
    header("Location: index.php");
    exit();
}

$currentUser = $_SESSION['user'];

// リンクIDの取得
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$linkId = $_GET['id'];

// seisei.jsonの読み込み
$seiseiData = json_decode(file_get_contents($seiseiFile), true);
if (!isset($seiseiData[$currentUser][$linkId])) {
    header("Location: index.php");
    exit();
}

$linkData = $seiseiData[$currentUser][$linkId];

// エラーメッセージの初期化
$errors = [];
$success = false;

// リンク編集処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_link'])) {
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
    $imagePath = $linkData['imagePath']; // 現在の画像パスを保持
    if ($imageOption === 'url') {
        $imageUrl = filter_var($_POST['imageUrl'], FILTER_SANITIZE_URL);
        if (empty($imageUrl) || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $errors[] = "有効な画像URLを入力してください。";
        } else {
            $imageData = file_get_contents_curl($imageUrl);
            if ($imageData === false) {
                $errors[] = "画像を取得できませんでした。";
            } else {
                $imagePath = saveImage($imageData, $baseDir . '/uploads');
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
                $imagePath = saveImage($imageData, $baseDir . '/uploads');
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
        $imagePath = saveImage($imageData, $baseDir . '/uploads');
    }

    if (empty($imagePath)) {
        $errors[] = "画像の処理に失敗しました。";
    }

    // リンク更新
    if (empty($errors)) {
        $seiseiData[$currentUser][$linkId] = [
            "linkA" => $linkA,
            "title" => $title,
            "description" => $description,
            "twitterSite" => $twitterSite,
            "imageAlt" => $imageAlt,
            "imagePath" => $imagePath,
            "created_at" => $linkData['created_at']
        ];
        file_put_contents($seiseiFile, json_encode($seiseiData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // リダイレクト用のindex.phpを更新
        $dirPath = $baseDir . '/../' . $linkId;
        if (file_exists($dirPath . '/index.php')) {
            $redirectContent = generateRedirectPage($linkA, $title, $description, $twitterSite, $imageAlt, $imagePath);
            file_put_contents($dirPath . '/index.php', $redirectContent);
        }

        $success = true;
        $linkData = $seiseiData[$currentUser][$linkId];
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
    <title>リンク編集 - <?php echo htmlspecialchars($linkData['title'], ENT_QUOTES, 'UTF-8'); ?></title>
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
            padding: 20px;
        }
        .container {
            width: 90%;
            max-width: 500px;
            padding: 20px;
            background-color: #1e1e1e;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            animation: fadeInUp 0.5s;
            margin: 0 auto;
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
            // ユーザー編集モーダルの処理（必要に応じて）
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>管理者ダッシュボード</h1>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($successMessage)): ?>
            <div class="success-message">
                <p><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>

        <!-- 新規ユーザー作成フォーム -->
        <form method="POST">
            <h2>新規ユーザーの作成</h2>
            <label>ユーザー名</label>
            <input type="text" name="new_username" required>

            <label>パスワード</label>
            <input type="password" name="new_password" required>

            <button type="submit" name="create_user">ユーザーを作成</button>
        </form>

        <!-- ユーザー一覧 -->
        <h2>ユーザー一覧</h2>
        <table>
            <tr>
                <th>ユーザー名</th>
                <th>管理者</th>
                <th>操作</th>
            </tr>
            <?php foreach ($userList as $username => $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo $user['is_admin'] ? 'はい' : 'いいえ'; ?></td>
                    <td class="action-buttons">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="edit_user" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="edit">編集</button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="delete_user" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="delete">削除</button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="reset_password" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="reset">パスワードリセット</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <!-- アクセスログ -->
        <div class="logs">
            <h2>アクセスログ</h2>
            <table>
                <tr>
                    <th>ユーザー名</th>
                    <th>アクション</th>
                    <th>タイムスタンプ</th>
                </tr>
                <?php if (!empty($logs)): ?>
                    <?php foreach (array_reverse($logs) as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['user'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($log['timestamp'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">ログがありません。</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- バックアップボタン -->
        <form method="POST">
            <button type="submit" name="backup" class="backup-button">データのバックアップ</button>
        </form>

        <!-- 管理者ログアウトボタン -->
        <form method="GET" style="margin-top: 20px;">
            <button type="submit" name="action" value="logout" class="logout-button">ログアウト</button>
        </form>
    </div>
</body>
</html>
