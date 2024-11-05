<?php
// セッションの開始
session_start();

// ユーザー情報を格納するJSONファイル
$usersFile = '../users.json';
$seiseiFile = '../seisei.json';

// 管理者認証
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// ユーザー情報の取得
$usersData = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];

// リンクデータの取得
$seiseiData = file_exists($seiseiFile) ? json_decode(file_get_contents($seiseiFile), true) : [];

// 新規ユーザー作成処理
$adminErrors = [];
$adminSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $newUserId = $_POST['new_user_id'] ?? '';
    $newUserPassword = $_POST['new_user_password'] ?? '';

    if (empty($newUserId) || empty($newUserPassword)) {
        $adminErrors[] = 'ユーザーIDとパスワードを入力してください。';
    } elseif (isset($usersData[$newUserId])) {
        $adminErrors[] = '既に存在するユーザーIDです。';
    } else {
        $usersData[$newUserId] = [
            'password' => $newUserPassword,
            'force_change' => true
        ];
        file_put_contents($usersFile, json_encode($usersData, JSON_PRETTY_PRINT));
        $adminSuccess = '新しいユーザーが作成されました。';
    }
}

// ユーザー情報の削除処理（オプション）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $deleteUserId = $_POST['delete_user_id'] ?? '';
    if ($deleteUserId === 'admin') {
        $adminErrors[] = '管理者アカウントは削除できません。';
    } elseif (isset($usersData[$deleteUserId])) {
        unset($usersData[$deleteUserId]);
        file_put_contents($usersFile, json_encode($usersData, JSON_PRETTY_PRINT));
        $adminSuccess = 'ユーザーが削除されました。';
    } else {
        $adminErrors[] = 'ユーザーが存在しません。';
    }
}

// ユーザーのリンク情報取得
function getUserLinks($userId, $seiseiData) {
    return isset($seiseiData[$userId]) ? $seiseiData[$userId] : [];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>管理者ダッシュボード</title>
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
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            animation: fadeIn 1s ease-in-out;
        }
        h1, h2 {
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            animation: fadeIn 0.5s;
        }
        table, th, td {
            border: 1px solid #ffffff;
        }
        th, td {
            padding: 10px;
            text-align: center;
        }
        .link-details {
            display: none;
            margin-top: 10px;
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
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // リンク詳細表示切替
            const viewButtons = document.querySelectorAll('.view-links-button');
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.dataset.userId;
                    const detailsDiv = document.getElementById('details_' + userId);
                    if (detailsDiv.style.display === 'none' || detailsDiv.style.display === '') {
                        detailsDiv.style.display = 'block';
                        this.textContent = '閉じる';
                    } else {
                        detailsDiv.style.display = 'none';
                        this.textContent = 'リンクを表示';
                    }
                });
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>管理者ダッシュボード</h1>

        <!-- 新規ユーザー作成フォーム -->
        <h2>新規ユーザー作成</h2>
        <?php if (!empty($adminErrors)): ?>
            <div class="error">
                <?php foreach ($adminErrors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($adminSuccess)): ?>
            <div class="success-message">
                <p><?php echo $adminSuccess; ?></p>
            </div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="action" value="create_user">
            <label>新規ユーザーID</label>
            <input type="text" name="new_user_id" required>

            <label>新規ユーザーパスワード</label>
            <input type="password" name="new_user_password" required>

            <button type="submit">ユーザーを作成</button>
        </form>

        <!-- ユーザー一覧 -->
        <h2 style="margin-top: 40px;">ユーザー一覧</h2>
        <?php if (count($usersData) > 0): ?>
            <table>
                <tr>
                    <th>ID</th>
                    <th>リンク生成数</th>
                    <th>操作</th>
                </tr>
                <?php foreach ($usersData as $userIdKey => $userInfo): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($userIdKey, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo getUserLinkCount($userIdKey, $seiseiFile); ?></td>
                        <td>
                            <button class="view-links-button" data-user-id="<?php echo htmlspecialchars($userIdKey, ENT_QUOTES, 'UTF-8'); ?>">リンクを表示</button>
                        </td>
                    </tr>
                    <tr id="details_<?php echo htmlspecialchars($userIdKey, ENT_QUOTES, 'UTF-8'); ?>" class="link-details">
                        <td colspan="3">
                            <?php
                            $userLinks = getUserLinks($userIdKey, $seiseiData);
                            if (count($userLinks) > 0) {
                                echo '<table>';
                                echo '<tr><th>ID</th><th>リンク</th><th>タイトル</th><th>作成日時</th></tr>';
                                foreach ($userLinks as $link) {
                                    echo '<tr>';
                                    echo '<td>' . htmlspecialchars($link['id'], ENT_QUOTES, 'UTF-8') . '</td>';
                                    echo '<td><a href="' . htmlspecialchars($link['link'], ENT_QUOTES, 'UTF-8') . '" target="_blank">' . htmlspecialchars($link['link'], ENT_QUOTES, 'UTF-8') . '</a></td>';
                                    echo '<td>' . htmlspecialchars($link['title'], ENT_QUOTES, 'UTF-8') . '</td>';
                                    echo '<td>' . htmlspecialchars($link['created_at'], ENT_QUOTES, 'UTF-8') . '</td>';
                                    echo '</tr>';
                                }
                                echo '</table>';
                            } else {
                                echo '<p>リンクが生成されていません。</p>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p>ユーザーが存在しません。</p>
        <?php endif; ?>

        <!-- ログアウトリンク -->
        <p style="margin-top:20px;"><a href="../index.php?action=logout" style="color:#00e5ff;">ログアウト</a></p>
    </div>
</body>
</html>
