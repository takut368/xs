<?php
// セッションの開始
session_start();

// 管理者認証のチェック
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] !== true) {
    // ログインページへリダイレクト
    header('Location: ../index.php');
    exit();
}

// ユーザー一覧の取得
$users = json_decode(file_get_contents('../users.json'), true);
if (!$users) {
    $users = [];
}

// リンク生成データの取得
$seisei = json_decode(file_get_contents('../seisei.json'), true);
if (!$seisei) {
    $seisei = [];
}

// 新規ユーザー作成処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $new_user_id = htmlspecialchars($_POST['new_user_id'], ENT_QUOTES, 'UTF-8');
    $new_user_password = $_POST['new_user_password'];
    
    if (empty($new_user_id) || empty($new_user_password)) {
        $admin_error = "IDとパスワードを入力してください。";
    } elseif (isset($users[$new_user_id])) {
        $admin_error = "既に存在するIDです。";
    } else {
        // パスワードのハッシュ化
        $hashed_password = password_hash($new_user_password, PASSWORD_DEFAULT);
        // 認証トークンの生成
        $auth_token = bin2hex(random_bytes(16));
        // ユーザー情報の追加
        $users[$new_user_id] = [
            'password' => $hashed_password,
            'auth_token' => $auth_token,
            'is_admin' => false,
            'password_change_required' => true
        ];
        // users.jsonに保存
        file_put_contents('../users.json', json_encode($users, JSON_PRETTY_PRINT));
        $admin_success = "ユーザーが作成されました。";
    }
}

// ユーザーのリンク数の取得
$user_link_counts = [];
foreach ($seisei as $uid => $links) {
    $user_link_counts[$uid] = count($links);
}

// リンク詳細の取得
if (isset($_GET['user'])) {
    $selected_user = $_GET['user'];
    if (isset($seisei[$selected_user])) {
        $selected_user_links = $seisei[$selected_user];
    } else {
        $selected_user_links = [];
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>管理者 - サムネイル付きリンク生成サービス</title>
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
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            animation: fadeIn 1s ease-in-out;
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
            font-size: 28px;
        }
        h2 {
            margin-top: 30px;
            font-size: 22px;
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
        .create-button {
            background: linear-gradient(to right, #ff4081, #f50057);
            color: #ffffff;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            padding: 15px;
            margin-top: 20px;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
        }
        .create-button:hover {
            background: linear-gradient(to right, #f50057, #ff4081);
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
        .users-table,
        .links-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .users-table th,
        .users-table td,
        .links-table th,
        .links-table td {
            border: 1px solid #444;
            padding: 10px;
            text-align: left;
        }
        .users-table th,
        .links-table th {
            background-color: #333;
        }
        .view-links-button {
            background: linear-gradient(to right, #00e5ff, #00b0ff);
            color: #000;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            padding: 5px 10px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .view-links-button:hover {
            background: linear-gradient(to right, #00b0ff, #00e5ff);
            transform: scale(1.02);
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
            max-width: 800px;
            animation: scaleUp 0.3s ease-in-out;
        }
        .close {
            color: #ffffff;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        /* テーブルスタイル */
        .links-table th,
        .links-table td {
            border: 1px solid #444;
            padding: 10px;
            text-align: left;
        }
        .links-table th {
            background-color: #333;
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
        @keyframes shake {
            0% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            50% { transform: translateX(5px); }
            75% { transform: translateX(-5px); }
            100% { transform: translateX(0); }
        }
    </style>
    <script>
        // JavaScriptをここに記述
        document.addEventListener('DOMContentLoaded', function() {
            // ユーザーのリンク詳細モーダルの処理
            const viewLinksButtons = document.querySelectorAll('.view-links-button');
            viewLinksButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const user = this.dataset.user;
                    document.getElementById('linksModal_' + user).style.display = 'block';
                });
            });

            const closeButtons = document.querySelectorAll('.modal .close');
            closeButtons.forEach(closeBtn => {
                closeBtn.addEventListener('click', function() {
                    this.parentElement.parentElement.style.display = 'none';
                });
            });

            window.addEventListener('click', function(event) {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    if (event.target == modal) {
                        modal.style.display = 'none';
                    }
                });
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>管理者ページ</h1>
        <?php if (isset($admin_error)): ?>
            <div class="error">
                <p><?php echo $admin_error; ?></p>
            </div>
        <?php endif; ?>
        <?php if (isset($admin_success)): ?>
            <div class="success">
                <p><?php echo $admin_success; ?></p>
            </div>
        <?php endif; ?>

        <!-- 新規ユーザー作成フォーム -->
        <h2>新規ユーザー作成</h2>
        <form method="POST" action="index.php">
            <label for="new_user_id">ユーザーID</label>
            <input type="text" id="new_user_id" name="new_user_id" required>

            <label for="new_user_password">パスワード</label>
            <input type="password" id="new_user_password" name="new_user_password" required>

            <button type="submit" name="create_user" class="create-button">ユーザーを作成</button>
        </form>

        <!-- ユーザー一覧 -->
        <h2>ユーザー一覧</h2>
        <table class="users-table">
            <tr>
                <th>ID</th>
                <th>リンク生成数</th>
                <th>操作</th>
            </tr>
            <?php foreach ($users as $uid => $user): ?>
                <?php if ($uid === 'admin') continue; // adminユーザーを除外 ?>
                <tr>
                    <td><?php echo htmlspecialchars($uid, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo isset($user_link_counts[$uid]) ? $user_link_counts[$uid] : 0; ?></td>
                    <td><button class="view-links-button" data-user="<?php echo htmlspecialchars($uid, ENT_QUOTES, 'UTF-8'); ?>">リンクを表示</button></td>
                </tr>

                <!-- リンク詳細モーダル -->
                <div id="linksModal_<?php echo htmlspecialchars($uid, ENT_QUOTES, 'UTF-8'); ?>" class="modal">
                    <div class="modal-content">
                        <span class="close">&times;</span>
                        <h2><?php echo htmlspecialchars($uid, ENT_QUOTES, 'UTF-8'); ?>の生成リンク一覧</h2>
                        <?php
                        if (isset($seisei[$uid])) {
                            echo '<table class="links-table">';
                            echo '<tr><th>タイトル</th><th>遷移先URL</th><th>作成日時</th><th>更新日時</th></tr>';
                            foreach ($seisei[$uid] as $link_id => $link) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($link['title'], ENT_QUOTES, 'UTF-8') . '</td>';
                                echo '<td><a href="' . htmlspecialchars($link['linkA'], ENT_QUOTES, 'UTF-8') . '" target="_blank">' . htmlspecialchars($link['linkA'], ENT_QUOTES, 'UTF-8') . '</a></td>';
                                echo '<td>' . $link['created_at'] . '</td>';
                                echo '<td>' . ($link['updated_at'] ?? '未更新') . '</td>';
                                echo '</tr>';
                            }
                            echo '</table>';
                        } else {
                            echo '<p>リンクは生成されていません。</p>';
                        }
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>
