<?php
// セッションの開始
session_start();

// データディレクトリのパス
define('DATA_DIR', __DIR__ . '/../data/');

// 必要なフォルダとファイルの自動作成
function autoCreateFilesAndFolders() {
    $folders = ['uploads', 'temp', 'data', 'backup'];
    foreach ($folders as $folder) {
        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }
    }

    // 初期データファイルの作成
    $dataFiles = [
        'users.json' => json_encode([
            'admin' => [
                'password' => 'admin', // 平文で管理
                'force_reset' => false
            ]
        ], JSON_PRETTY_PRINT),
        'seisei.json' => json_encode([], JSON_PRETTY_PRINT),
        'logs.json' => json_encode([], JSON_PRETTY_PRINT)
    ];

    foreach ($dataFiles as $file => $content) {
        if (!file_exists(DATA_DIR . $file)) {
            file_put_contents(DATA_DIR . $file, $content);
        }
    }

    // 初期テンプレート画像の確認（既に配置されている前提）
    $templateImages = ['live_now.png', 'nude.png', 'gigafile.jpg', 'ComingSoon.png'];
    foreach ($templateImages as $image) {
        if (!file_exists('../temp/' . $image)) {
            // ダミー画像を作成（実際には適切なテンプレート画像を配置してください）
            $img = imagecreatetruecolor(400, 200);
            $bgColor = imagecolorallocate($img, 50, 50, 50);
            imagefilledrectangle($img, 0, 0, 400, 200, $bgColor);
            imagestring($img, 5, 150, 90, $image, imagecolorallocate($img, 255, 255, 255));
            imagepng($img, '../temp/' . $image);
            imagedestroy($img);
        }
    }
}

// 自動作成を実行
autoCreateFilesAndFolders();

// ユーザー情報とログを読み込む関数
function loadData($filename) {
    if (!file_exists(DATA_DIR . $filename)) {
        return [];
    }
    $data = file_get_contents(DATA_DIR . $filename);
    return json_decode($data, true);
}

// データ保存関数
function saveData($filename, $data) {
    file_put_contents(DATA_DIR . $filename, json_encode($data, JSON_PRETTY_PRINT));
}

// ログ記録関数
function logAction($action, $username = 'Guest') {
    $logs = loadData('logs.json');
    $logs[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user' => $username,
        'action' => $action
    ];
    saveData('logs.json', $logs);
}

// 認証チェック
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$adminErrors = [];
$adminSuccess = '';

// ユーザー作成
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $newUsername = trim($_POST['new_username']);
    $newPassword = $_POST['new_password'];

    // 管理者のIDとパスワードは変更できない
    if ($newUsername === 'admin') {
        $adminErrors[] = '管理者ユーザーは作成できません。';
    } elseif (empty($newUsername) || empty($newPassword)) {
        $adminErrors[] = 'ユーザーIDとパスワードを入力してください。';
    } else {
        $users = loadData('users.json');
        if (isset($users[$newUsername])) {
            $adminErrors[] = '既に存在するユーザーIDです。';
        } else {
            $users[$newUsername] = [
                'password' => $newPassword, // 平文で管理
                'force_reset' => true
            ];
            saveData('users.json', $users);
            $adminSuccess = 'ユーザーを作成しました。';
            logAction('ユーザー作成: ' . $newUsername, 'admin');
        }
    }
}

// ユーザー削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $deleteUsername = $_POST['delete_username'];

    if ($deleteUsername === 'admin') {
        $adminErrors[] = '管理者ユーザーは削除できません。';
    } else {
        $users = loadData('users.json');
        if (isset($users[$deleteUsername])) {
            unset($users[$deleteUsername]);
            saveData('users.json', $users);
            $adminSuccess = 'ユーザーを削除しました。';
            logAction('ユーザー削除: ' . $deleteUsername, 'admin');
        } else {
            $adminErrors[] = '指定されたユーザーが存在しません。';
        }
    }
}

// ユーザー編集（パスワード変更）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $editUsername = $_POST['edit_username'];
    $editPassword = $_POST['edit_password'];

    if ($editUsername === 'admin') {
        $adminErrors[] = '管理者ユーザーのパスワードは変更できません。';
    } elseif (empty($editPassword)) {
        $adminErrors[] = '新しいパスワードを入力してください。';
    } else {
        $users = loadData('users.json');
        if (isset($users[$editUsername])) {
            $users[$editUsername]['password'] = $editPassword; // 平文で管理
            $users[$editUsername]['force_reset'] = true; // 次回ログイン時にパスワード変更を要求
            saveData('users.json', $users);
            $adminSuccess = 'ユーザーのパスワードを更新しました。';
            logAction('ユーザー編集（パスワード更新）: ' . $editUsername, 'admin');
        } else {
            $adminErrors[] = '指定されたユーザーが存在しません。';
        }
    }
}

// パスワードリセット
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $resetUsername = $_POST['reset_username'];
    $newPassword = $_POST['reset_password'];

    if ($resetUsername === 'admin') {
        $adminErrors[] = '管理者ユーザーのパスワードはリセットできません。';
    } elseif (empty($newPassword)) {
        $adminErrors[] = '新しいパスワードを入力してください。';
    } else {
        $users = loadData('users.json');
        if (isset($users[$resetUsername])) {
            $users[$resetUsername]['password'] = $newPassword; // 平文で管理
            $users[$resetUsername]['force_reset'] = true;
            saveData('users.json', $users);
            $adminSuccess = 'ユーザーのパスワードをリセットしました。';
            logAction('パスワードリセット: ' . $resetUsername, 'admin');
        } else {
            $adminErrors[] = '指定されたユーザーが存在しません。';
        }
    }
}

// アクセスログの取得
$logs = loadData('logs.json');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>管理者画面 - サムネイル付きリンク生成サービス</title>
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
            background-color: #1e1e1e;
            color: #ffffff;
            font-family: 'Helvetica Neue', Arial, sans-serif;
            overflow-x: hidden;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            animation: fadeIn 1s ease-in-out;
        }
        h1, h2 {
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
        .section h2 {
            margin-bottom: 15px;
            text-align: center;
        }
        label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
            font-size: 16px;
        }
        input[type="text"],
        input[type="password"],
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
        input[type="password"]:focus,
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
            width: 100%;
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
        .success-message {
            background-color: #3a3a3a;
            color: #ffffff;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            animation: fadeInUp 0.5s;
            text-align: center;
        }
        .success-message input[type="text"] {
            width: 70%;
            margin-top: 10px;
            display: inline-block;
            background-color: #2a2a2a;
            border: none;
            border-radius: 5px;
            padding: 10px;
        }
        .success-message button {
            width: 25%;
            margin-left: 5%;
            display: inline-block;
            padding: 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .success-message button:hover {
            background-color: #45a049;
        }
        .preview-image {
            max-width: 100%;
            border-radius: 5px;
            margin-top: 20px;
            animation: fadeIn 0.5s;
        }
        /* テーブルスタイル */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table, th, td {
            border: 1px solid #555;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #4a4a4a;
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
            background-color: #2a2a2a;
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
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
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

            // セッションタイムアウト（30分）
            setInterval(function() {
                const now = new Date().getTime();
                const timeout = 30 * 60 * 1000; // 30分
                if (sessionStorage.getItem('lastActivity')) {
                    const lastActivity = parseInt(sessionStorage.getItem('lastActivity'));
                    if (now - lastActivity > timeout) {
                        alert('セッションがタイムアウトしました。再度ログインしてください。');
                        window.location.href = 'index.php';
                    }
                }
                sessionStorage.setItem('lastActivity', now);
            }, 5 * 60 * 1000); // チェックは5分ごと
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>サムネイル付きリンク生成サービス</h1>
        <?php if (!isset($_SESSION['username'])): ?>
            <!-- ログインフォーム -->
            <form method="POST">
                <input type="hidden" name="login" value="1">
                <label for="username">ユーザーID</label>
                <input type="text" id="username" name="username" required>

                <label for="password">パスワード</label>
                <input type="password" id="password" name="password" required>

                <button type="submit">ログイン</button>
            </form>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php elseif ($isAdmin): ?>
            <!-- 管理者は admin/index.php にリダイレクトされるため、ここには表示しません -->
        <?php else: ?>
            <?php
                // ユーザーが初回ログイン時にパスワード変更が必要な場合
                if ($users[$username]['force_reset']):
            ?>
                <!-- パスワード変更フォーム -->
                <form method="POST">
                    <input type="hidden" name="reset_password" value="1">
                    <label for="new_password">新しいパスワード</label>
                    <input type="password" id="new_password" name="new_password" required>

                    <label for="confirm_password">パスワード確認</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>

                    <button type="submit">パスワード変更</button>
                </form>
                <?php if ($success): ?>
                    <div class="success-message">
                        <p><?php echo htmlspecialchars($success); ?></p>
                        <form method="POST">
                            <button type="submit" name="logout">ログアウト</button>
                        </form>
                    </div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- 一般ユーザーのリンク生成・管理 -->
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="generate" value="1">
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

                    <div class="details-section" id="detailsSection">
                        <label>ページの説明</label>
                        <textarea name="description"></textarea>

                        <label>Twitterアカウント名（@を含む）</label>
                        <input type="text" name="twitterSite">

                        <label>画像の代替テキスト</label>
                        <input type="text" name="imageAlt">
                    </div>

                    <button type="button" id="toggleDetails">詳細設定</button>
                    <button type="submit">リンクを生成</button>
                </form>

                <?php if ($success): ?>
                    <div class="success-message">
                        <p>リンクが生成されました：</p>
                        <input type="text" id="generatedLink" value="<?php echo htmlspecialchars($generatedLink); ?>" readonly>
                        <button id="copyButton">コピー</button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- リンク一覧 -->
                <h2>生成したリンク一覧</h2>
                <form method="GET">
                    <input type="text" name="search" placeholder="タイトルまたはURLで検索" value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    <button type="submit">検索</button>
                </form>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>タイトル</th>
                            <th>URL</th>
                            <th>生成日時</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($filteredLinks)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">リンクがありません。</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($filteredLinks as $id => $link): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($id); ?></td>
                                    <td><?php echo htmlspecialchars($link['title']); ?></td>
                                    <td><a href="<?php echo htmlspecialchars($link['linkA']); ?>" target="_blank">遷移先URL</a></td>
                                    <td><?php echo htmlspecialchars($link['created_at']); ?></td>
                                    <td>
                                        <a href="index.php?action=edit&id=<?php echo urlencode($id); ?>" style="color: #4CAF50;">編集</a> |
                                        <a href="index.php?action=delete&id=<?php echo urlencode($id); ?>" style="color: #f44336;" onclick="return confirm('本当に削除しますか？');">削除</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- リンク編集フォーム -->
                <?php if (isset($editLink)): ?>
                    <form method="POST">
                        <input type="hidden" name="edit_link" value="1">
                        <input type="hidden" name="edit_id" value="<?php echo htmlspecialchars($editId); ?>">

                        <label>遷移先URL（必須）</label>
                        <input type="url" name="edit_linkA" value="<?php echo htmlspecialchars($editLink['linkA']); ?>" required>

                        <label>タイトル（必須）</label>
                        <input type="text" name="edit_title" value="<?php echo htmlspecialchars($editLink['title']); ?>" required>

                        <label>サムネイル画像の選択方法（必須）</label>
                        <div class="image-option-buttons">
                            <button type="button" class="image-option-button" data-option="url">画像URLを入力</button>
                            <button type="button" class="image-option-button" data-option="upload">画像ファイルをアップロード</button>
                            <button type="button" class="image-option-button" data-option="template">テンプレートから選択</button>
                        </div>

                        <div id="imageUrlInput" style="display:none;">
                            <label>画像URLを入力</label>
                            <input type="url" name="edit_imageUrl" value="">
                        </div>

                        <div id="imageFileInput" style="display:none;">
                            <label>画像ファイルをアップロード</label>
                            <input type="file" name="edit_imageFile" accept="image/*">
                        </div>

                        <div class="details-section" id="detailsSection">
                            <label>ページの説明</label>
                            <textarea name="edit_description"><?php echo htmlspecialchars($editLink['description']); ?></textarea>

                            <label>Twitterアカウント名（@を含む）</label>
                            <input type="text" name="edit_twitterSite" value="<?php echo htmlspecialchars($editLink['twitterSite']); ?>">

                            <label>画像の代替テキスト</label>
                            <input type="text" name="edit_imageAlt" value="<?php echo htmlspecialchars($editLink['imageAlt']); ?>">
                        </div>

                        <button type="button" id="toggleDetails">詳細設定</button>
                        <button type="submit">リンクを更新</button>
                    </form>
                <?php endif; ?>

                <!-- ログアウトボタン -->
                <form method="POST">
                    <button type="submit" name="logout" style="background-color: #f44336;">ログアウト</button>
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
                        <img src="../temp/<?php echo htmlspecialchars($template); ?>" alt="<?php echo htmlspecialchars($template); ?>">
                        <input type="radio" name="templateRadio" value="<?php echo htmlspecialchars($template); ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
