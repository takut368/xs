<?php
// セッションの開始
session_start();

// 自動作成関数（念のため）
function autoCreateFilesAndFolders() {
    $folders = ['uploads', 'temp', 'data', 'backup'];
    foreach ($folders as $folder) {
        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }
    }

    // 初期データファイルの作成
    $dataFiles = [
        'data/users.json' => json_encode([
            'admin' => [
                'password' => 'admin', // 平文で管理
                'force_reset' => false
            ]
        ], JSON_PRETTY_PRINT),
        'data/seisei.json' => json_encode([], JSON_PRETTY_PRINT),
        'data/logs.json' => json_encode([], JSON_PRETTY_PRINT)
    ];

    foreach ($dataFiles as $file => $content) {
        if (!file_exists($file)) {
            file_put_contents($file, $content);
        }
    }

    // 初期テンプレート画像の確認（既に配置されている前提）
    $templateImages = ['live_now.png', 'nude.png', 'gigafile.jpg', 'ComingSoon.png'];
    foreach ($templateImages as $image) {
        if (!file_exists('temp/' . $image)) {
            // ダミー画像を作成（実際には適切なテンプレート画像を配置してください）
            $img = imagecreatetruecolor(400, 200);
            $bgColor = imagecolorallocate($img, 50, 50, 50);
            imagefilledrectangle($img, 0, 0, 400, 200, $bgColor);
            imagestring($img, 5, 150, 90, $image, imagecolorallocate($img, 255, 255, 255));
            imagepng($img, 'temp/' . $image);
            imagedestroy($img);
        }
    }
}

// 自動作成を実行
autoCreateFilesAndFolders();

// ユーザー情報とログを読み込む関数
function loadData($filename) {
    if (!file_exists($filename)) {
        return [];
    }
    $data = file_get_contents($filename);
    return json_decode($data, true);
}

// データ保存関数
function saveData($filename, $data) {
    file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
}

// ログ記録関数
function logAction($action, $username = 'Guest') {
    $logs = loadData('data/logs.json');
    $logs[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user' => $username,
        'action' => $action
    ];
    saveData('data/logs.json', $logs);
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
        $users = loadData('data/users.json');
        if (isset($users[$newUsername])) {
            $adminErrors[] = '既に存在するユーザーIDです。';
        } else {
            $users[$newUsername] = [
                'password' => $newPassword, // 平文で管理
                'force_reset' => true
            ];
            saveData('data/users.json', $users);
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
        $users = loadData('data/users.json');
        if (isset($users[$deleteUsername])) {
            unset($users[$deleteUsername]);
            saveData('data/users.json', $users);
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
        $users = loadData('data/users.json');
        if (isset($users[$editUsername])) {
            $users[$editUsername]['password'] = $editPassword; // 平文で管理
            $users[$editUsername]['force_reset'] = true; // 次回ログイン時にパスワード変更を要求
            saveData('data/users.json', $users);
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
        $users = loadData('data/users.json');
        if (isset($users[$resetUsername])) {
            $users[$resetUsername]['password'] = $newPassword; // 平文で管理
            $users[$resetUsername]['force_reset'] = true;
            saveData('data/users.json', $users);
            $adminSuccess = 'ユーザーのパスワードをリセットしました。';
            logAction('パスワードリセット: ' . $resetUsername, 'admin');
        } else {
            $adminErrors[] = '指定されたユーザーが存在しません。';
        }
    }
}

// アクセスログの取得
$logs = loadData('data/logs.json');
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
        .success-message p {
            margin-bottom: 10px;
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
            z-index: 2000;
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
        /* テーブルスクロール */
        .table-responsive {
            overflow-x: auto;
        }
        /* ボタンスタイル */
        .btn {
            padding: 10px 15px;
            margin: 5px 0;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-edit {
            background-color: #4CAF50;
            color: white;
        }
        .btn-edit:hover {
            background-color: #45a049;
        }
        .btn-delete {
            background-color: #f44336;
            color: white;
        }
        .btn-delete:hover {
            background-color: #da190b;
        }
        .btn-reset {
            background-color: #ff9800;
            color: white;
        }
        .btn-reset:hover {
            background-color: #e68900;
        }
        /* メディアクエリ */
        @media screen and (max-width: 600px) {
            .modal-content {
                width: 95%;
            }
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
                        // フォーム送信ではなく、POSTリクエストを送信する
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '';

                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'delete_user';
                        input.value = '1';
                        form.appendChild(input);

                        const userInput = document.createElement('input');
                        userInput.type = 'hidden';
                        userInput.name = 'delete_username';
                        userInput.value = username;
                        form.appendChild(userInput);

                        document.body.appendChild(form);
                        form.submit();
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
                <input type="password" id="new_password" name="new_password" required>

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
                <form method="POST">
                    <input type="hidden" name="edit_user" value="1">
                    <input type="hidden" name="edit_username" id="edit_username">
                    <label for="edit_password">新しいパスワード</label>
                    <input type="password" id="edit_password" name="edit_password" required>
                    <button type="submit">パスワード更新</button>
                </form>
            </div>
        </div>

        <!-- パスワードリセットモーダル -->
        <div id="resetModal" class="modal">
            <div class="modal-content">
                <span class="close" id="resetClose">&times;</span>
                <h2>パスワードリセット</h2>
                <form method="POST">
                    <input type="hidden" name="reset_password" value="1">
                    <input type="hidden" name="reset_username" id="reset_username">
                    <label for="reset_password">新しいパスワード</label>
                    <input type="password" id="reset_password" name="reset_password" required>
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
