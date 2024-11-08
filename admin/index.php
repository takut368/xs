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
    $newUserId = trim($_POST['new_user_id'] ?? '');
    $newUserPassword = trim($_POST['new_user_password'] ?? '');

    if (empty($newUserId) || empty($newUserPassword)) {
        $adminErrors[] = 'ユーザーIDとパスワードを入力してください。';
    } elseif (isset($usersData[$newUserId])) {
        $adminErrors[] = 'このユーザーIDは既に存在します。';
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

        // seisei.jsonのデータも削除
        if (isset($seiseiData[$deleteUserId])) {
            unset($seiseiData[$deleteUserId]);
            file_put_contents($seiseiFile, json_encode($seiseiData, JSON_PRETTY_PRINT));
        }

        $adminSuccess = 'ユーザーが削除されました。';
    } else {
        $adminErrors[] = '指定されたユーザーは存在しません。';
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

            // リンク編集モーダルの処理
            const editModal = document.getElementById('editModal');
            const editClose = document.getElementById('editClose');
            const editTemplateModal = document.getElementById('editTemplateModal');
            const editTemplateClose = document.getElementById('editTemplateClose');

            function editLink(linkId) {
                document.getElementById('edit_link_id').value = linkId;
                editModal.style.display = 'block';
            }

            editClose.addEventListener('click', function() {
                editModal.style.display = 'none';
            });

            editTemplateClose.addEventListener('click', function() {
                editTemplateModal.style.display = 'none';
            });

            window.addEventListener('click', function(event) {
                if (event.target == editModal) {
                    editModal.style.display = 'none';
                }
                if (event.target == editTemplateModal) {
                    editTemplateModal.style.display = 'none';
                }
            });

            const editTemplateItems = editTemplateModal.querySelectorAll('.template-item');
            editTemplateItems.forEach(item => {
                item.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    editTemplateModal.style.display = 'none';
                    // プレビュー表示
                    let preview = document.getElementById('edit_imagePreview');
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.id = 'edit_imagePreview';
                        preview.classList.add('preview-image');
                        editModal.querySelector('.modal-content').appendChild(preview);
                    }
                    preview.src = this.querySelector('img').src;
                    // サーバーに送信するselectedTemplateの値を設定
                    document.getElementById('new_selectedTemplate').value = radio.value;
                });
            });

            // 編集リンクの画像選択ボタン処理
            const editImageOptionButtons = editModal.querySelectorAll('.image-option-button');
            editImageOptionButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // クラスの切り替え
                    editImageOptionButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    const selectedOption = this.dataset.option;
                    document.getElementById('edit_imageOptionInput').value = selectedOption;

                    // 各オプションの表示・非表示
                    document.getElementById('edit_imageUrlInput').style.display = 'none';
                    document.getElementById('edit_imageFileInput').style.display = 'none';

                    if (selectedOption === 'url') {
                        document.getElementById('edit_imageUrlInput').style.display = 'block';
                    } else if (selectedOption === 'upload') {
                        document.getElementById('edit_imageFileInput').style.display = 'block';
                    } else if (selectedOption === 'template') {
                        // テンプレート選択モーダルを表示
                        openEditTemplateModal();
                    }
                });
            });

            function openEditTemplateModal() {
                editTemplateModal.style.display = 'block';
            }
        </script>

        <!-- リンク編集モーダル -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close" id="editClose">&times;</span>
                <h2>リンクを編集</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="edit_link">
                    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                    <input type="hidden" id="edit_link_id" name="link_id">

                    <label>遷移先URL（必須）</label>
                    <input type="url" name="new_redirect_url" required>

                    <label>タイトル（必須）</label>
                    <input type="text" name="new_title" required>

                    <label>サムネイル画像の選択方法（必須）</label>
                    <div class="image-option-buttons">
                        <button type="button" class="image-option-button" data-option="url">画像URLを入力</button>
                        <button type="button" class="image-option-button" data-option="upload">画像ファイルをアップロード</button>
                        <button type="button" class="image-option-button" data-option="template">テンプレートから選択</button>
                    </div>

                    <div id="edit_imageUrlInput" style="display:none;">
                        <label>画像URLを入力</label>
                        <input type="url" name="new_imageUrl">
                    </div>

                    <div id="edit_imageFileInput" style="display:none;">
                        <label>画像ファイルをアップロード</label>
                        <input type="file" name="new_imageFile" accept="image/*">
                    </div>

                    <!-- テンプレート選択モーダル -->
                    <div id="editTemplateModal" class="modal">
                        <div class="modal-content">
                            <span class="close" id="editTemplateClose">&times;</span>
                            <h2>テンプレートを選択</h2>
                            <div class="template-grid">
                                <?php
                                foreach ($templates as $template):
                                ?>
                                    <div class="template-item">
                                        <img src="../temp/<?php echo $template; ?>" alt="<?php echo $template; ?>">
                                        <input type="radio" name="new_templateRadio" value="<?php echo $template; ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <label>ページの説明</label>
                    <textarea name="new_description"></textarea>

                    <label>Twitterアカウント名（@を含む）</label>
                    <input type="text" name="new_twitterSite">

                    <label>画像の代替テキスト</label>
                    <input type="text" name="new_imageAlt">

                    <button type="submit">更新</button>
                </form>
            </div>
        </div>
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

            <!-- ユーザー削除フォーム（オプション） -->
            <!--
            <h2 style="margin-top: 40px;">ユーザー削除</h2>
            <form method="POST">
                <input type="hidden" name="action" value="delete_user">
                <label>削除するユーザーID</label>
                <input type="text" name="delete_user_id" required>
                <button type="submit">ユーザーを削除</button>
            </form>
            -->

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
                                <?php if ($userIdKey !== 'admin'): ?>
                                    <button class="view-links-button" data-user-id="<?php echo htmlspecialchars($userIdKey, ENT_QUOTES, 'UTF-8'); ?>">リンクを表示</button>
                                <?php else: ?>
                                    管理者
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($userIdKey !== 'admin'): ?>
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
                        <?php endif; ?>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>ユーザーが存在しません。</p>
            <?php endif; ?>

            <script>
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
            </script>

            <!-- ログアウトリンク -->
            <p style="margin-top:20px;"><a href="../index.php?action=logout" style="text-decoration: none;"><button style="width:auto; padding: 10px 20px;">ログアウト</button></a></p>
        </div>
    </body>
</html>
