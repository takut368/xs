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
            // ユーザー編集モーダル
            const editModal = document.getElementById('editModal');
            const editClose = document.getElementById('editClose');
            const editButtons = document.querySelectorAll('.btn-edit');

            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const username = this.dataset.username;
                    document.getElementById('edit_username').value = username;
                    editModal.style.display = 'block';
                });
            });

            editClose.addEventListener('click', function() {
                editModal.style.display = 'none';
            });

            window.addEventListener('click', function(event) {
                if (event.target == editModal) {
                    editModal.style.display = 'none';
                }
            });

            // ユーザー削除確認
            const deleteButtons = document.querySelectorAll('.btn-delete');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const username = this.dataset.username;
                    if (confirm('本当にユーザー「' + username + '」を削除しますか？')) {
                        window.location.href = 'delete_user.php?username=' + encodeURIComponent(username);
                    }
                });
            });

            // パスワードリセットモーダル
            const resetModal = document.getElementById('resetModal');
            const resetClose = document.getElementById('resetClose');
            const resetButtons = document.querySelectorAll('.btn-reset');

            resetButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const username = this.dataset.username;
                    document.getElementById('reset_username').value = username;
                    resetModal.style.display = 'block';
                });
            });

            resetClose.addEventListener('click', function() {
                resetModal.style.display = 'none';
            });

            window.addEventListener('click', function(event) {
                if (event.target == resetModal) {
                    resetModal.style.display = 'none';
                }
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>管理者画面</h1>

        <div class="section">
            <h2>ユーザー管理</h2>
            <?php if (!empty($adminErrors)): ?>
                <div class="error">
                    <?php foreach ($adminErrors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($adminSuccess): ?>
                <div class="success-message">
                    <p><?php echo htmlspecialchars($adminSuccess); ?></p>
                </div>
            <?php endif; ?>

            <!-- ユーザー作成フォーム -->
            <form method="POST">
                <label for="new_username">新規ユーザーID</label>
                <input type="text" id="new_username" name="new_username" required>

                <label for="new_password">新規ユーザーパスワード</label>
                <input type="text" id="new_password" name="new_password" required>

                <button type="submit" name="create_user">ユーザー作成</button>
            </form>

            <!-- ユーザー一覧 -->
            <h3 style="margin-top: 30px; text-align: center;">ユーザー一覧</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ユーザーID</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $users = loadData('data/users.json');
                        foreach ($users as $user => $details):
                            if ($user === 'admin') continue; // 管理者は表示しない
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user); ?></td>
                                <td>
                                    <button class="btn btn-edit" data-username="<?php echo htmlspecialchars($user); ?>">編集</button>
                                    <button class="btn btn-delete" data-username="<?php echo htmlspecialchars($user); ?>">削除</button>
                                    <button class="btn btn-reset" data-username="<?php echo htmlspecialchars($user); ?>">パスワードリセット</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="section">
            <h2>アクセスログ</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>日時</th>
                            <th>ユーザー</th>
                            <th>アクション</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $logs = loadData('data/logs.json');
                        rsort($logs); // 最新のログを上に
                        foreach ($logs as $log):
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['timestamp']); ?></td>
                                <td><?php echo htmlspecialchars($log['user']); ?></td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center;">ログがありません。</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ユーザー編集モーダル -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close" id="editClose">&times;</span>
                <h2>ユーザー編集</h2>
                <form method="POST" action="edit_user.php">
                    <input type="hidden" name="username" id="edit_username">
                    <label for="edit_password">新しいパスワード</label>
                    <input type="text" id="edit_password" name="edit_password" required>
                    <button type="submit">パスワード更新</button>
                </form>
            </div>
        </div>

        <!-- パスワードリセットモーダル -->
        <div id="resetModal" class="modal">
            <div class="modal-content">
                <span class="close" id="resetClose">&times;</span>
                <h2>パスワードリセット</h2>
                <form method="POST" action="reset_password.php">
                    <input type="hidden" name="username" id="reset_username">
                    <label for="reset_password">新しいパスワード</label>
                    <input type="text" id="reset_password" name="reset_password" required>
                    <button type="submit">パスワードリセット</button>
                </form>
            </div>
        </div>

        <!-- ログアウトボタン -->
        <form method="POST" action="../logout.php">
            <button type="submit" style="background-color: #f44336;">ログアウト</button>
        </form>
    </div>
</body>
</html>
