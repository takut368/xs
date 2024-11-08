<?php
// セッションの開始は最初に行います
session_start();

// クエリパラメータのチェック
if (!isset($_GET['id']) || $_GET['id'] !== '123') {
    // 画面を真っ白にして何も表示しない
    exit;
}

// CSRFトークンの生成
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

// エラーメッセージと成功メッセージの初期化
$errors = [];
$success = false;
$generatedLink = '';

// ログアウト処理
if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_unset();
    session_destroy();
    setcookie("loggedin", "", time() - 3600, "/");
    header("Location: index.php?id=123");
    exit;
}

// パスワード変更処理
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!hash_equals($_SESSION['token'], $_POST['token'])) {
        $errors[] = '不正なリクエストです。';
    } else {
        $new_id = trim($_POST['new_id']);
        $new_password = trim($_POST['new_password']);
        if (empty($new_id) || empty($new_password)) {
            $errors[] = '新しいIDとパスワードを入力してください。';
        } else {
            // users.jsonの読み込み
            $users = json_decode(file_get_contents('users.json'), true);
            foreach ($users as &$user) {
                if ($user['id'] === $_SESSION['user_id']) {
                    $user['id'] = $new_id;
                    $user['password'] = $new_password;
                    $user['first_login'] = false;
                    break;
                }
            }
            // users.jsonの書き込み
            file_put_contents('users.json', json_encode($users, JSON_PRETTY_PRINT));
            // セッション更新
            $_SESSION['user_id'] = $new_id;
            // クッキーの更新
            setcookie("loggedin", true, time() + (86400 * 30), "/"); // 30日間有効
            $success = true;
        }
    }
}

// ログイン処理
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    if (!hash_equals($_SESSION['token'], $_POST['token'])) {
        $errors[] = '不正なリクエストです。';
    } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        if ($username === 'admin' && $password === 'admin') {
            // 管理者ログイン
            $_SESSION['admin'] = true;
            setcookie("loggedin_admin", true, time() + (86400 * 30), "/"); // 30日間有効
            header("Location: admin/index.php");
            exit;
        } else {
            // ユーザーログイン
            $users = json_decode(file_get_contents('users.json'), true);
            $user_found = false;
            foreach ($users as $user) {
                if ($user['id'] === $username && $user['password'] === $password) {
                    $user_found = true;
                    $_SESSION['user_id'] = $username;
                    setcookie("loggedin", true, time() + (86400 * 30), "/"); // 30日間有効
                    if ($user['first_login']) {
                        // 初回ログイン時はパスワード変更フォームへ
                        header("Location: index.php?id=123&action=change_password");
                        exit;
                    }
                    break;
                }
            }
            if (!$user_found) {
                $errors[] = 'ユーザーIDまたはパスワードが間違っています。';
            }
        }
    }
}

// パスワード変更フォームへのリクエスト
if (isset($_GET['action']) && $_GET['action'] === 'change_password') {
    if (!isset($_SESSION['user_id'])) {
        // ログインしていない場合はログイン画面へ
        header("Location: index.php?id=123");
        exit;
    }
    // パスワード変更フォームを表示
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <title>パスワード変更</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            /* スタイルは後述 */
            /* ... */
        </style>
    </head>
    <body>
        <div class="container">
            <h1>パスワード変更</h1>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success-message">
                    <p>パスワードが正常に変更されました。</p>
                    <a href="index.php?id=123">ダッシュボードへ戻る</a>
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                    <input type="hidden" name="action" value="change_password">
                    <label>新しいユーザーID</label>
                    <input type="text" name="new_id" required>
                    <label>新しいパスワード</label>
                    <input type="text" name="new_password" required>
                    <button type="submit">変更</button>
                </form>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ユーザーがログインしている場合の処理
if (isset($_SESSION['user_id'])) {
    $current_user = $_SESSION['user_id'];

    // ユーザーのリンク情報を取得
    $links = json_decode(file_get_contents('seisei.json'), true);
    $user_links = array_filter($links, function($link) use ($current_user) {
        return $link['user_id'] === $current_user;
    });

    // リンク編集処理
    if (isset($_POST['action']) && $_POST['action'] === 'edit_link') {
        if (!hash_equals($_SESSION['token'], $_POST['token'])) {
            $errors[] = '不正なリクエストです。';
        } else {
            $unique_id = $_POST['unique_id'];
            $new_title = trim($_POST['new_title']);
            $new_target_url = trim($_POST['new_target_url']);

            if (empty($new_title) || empty($new_target_url)) {
                $errors[] = 'タイトルと遷移先URLを入力してください。';
            } else {
                // seisei.jsonの読み込み
                $links = json_decode(file_get_contents('seisei.json'), true);
                foreach ($links as &$link) {
                    if ($link['unique_id'] === $unique_id && $link['user_id'] === $current_user) {
                        $link['title'] = $new_title;
                        $link['target_url'] = $new_target_url;
                        break;
                    }
                }
                // seisei.jsonの書き込み
                file_put_contents('seisei.json', json_encode($links, JSON_PRETTY_PRINT));
                $success = true;
                // リンク情報を再取得
                $user_links = array_filter($links, function($link) use ($current_user) {
                    return $link['user_id'] === $current_user;
                });
            }
        }
    }

    // リンク生成処理
    if (isset($_POST['action']) && $_POST['action'] === 'generate_link') {
        if (!hash_equals($_SESSION['token'], $_POST['token'])) {
            $errors[] = '不正なリクエストです。';
        } else {
            $title = trim($_POST['title']);
            $target_url = trim($_POST['target_url']);
            $image_path = trim($_POST['image_path']);

            if (empty($title) || empty($target_url) || empty($image_path)) {
                $errors[] = 'タイトル、遷移先URL、サムネイル画像を入力してください。';
            } else {
                // ユニークなIDの生成
                $unique_id = uniqid();
                $dir_path = $unique_id;
                if (!mkdir($dir_path, 0777, true)) {
                    $errors[] = 'リンクフォルダの作成に失敗しました。';
                } else {
                    // リダイレクト用のindex.phpを生成
                    $redirect_php = "<?php
header('Location: " . addslashes($target_url) . "');
exit;
?>";
                    file_put_contents($dir_path . '/index.php', $redirect_php);

                    // リンク情報の保存
                    $links = json_decode(file_get_contents('seisei.json'), true);
                    $links[] = [
                        'unique_id' => $unique_id,
                        'user_id' => $current_user,
                        'title' => $title,
                        'target_url' => $target_url,
                        'image_path' => $image_path,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    file_put_contents('seisei.json', json_encode($links, JSON_PRETTY_PRINT));

                    $success = true;
                    // リンク情報を再取得
                    $user_links = array_filter($links, function($link) use ($current_user) {
                        return $link['user_id'] === $current_user;
                    });
                }
            }
        }
    }

    // ログアウト処理は上記で既に実装

    // ユーザーダッシュボードの表示
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <title>ユーザーダッシュボード</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            /* スタイルをここに記述 */
            /* 以下、index.phpで使用するスタイル */
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
                justify-content: space-between;
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
                flex: 1 1 48%;
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
            /* リンク一覧テーブル */
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            th, td {
                padding: 10px;
                text-align: left;
                border-bottom: 1px solid #333;
            }
            th {
                background-color: #2a2a2a;
            }
            tr:hover {
                background-color: #333;
            }
            /* 編集フォーム */
            .edit-form {
                margin-top: 20px;
                padding: 15px;
                background-color: #1e1e1e;
                border-radius: 10px;
                animation: fadeIn 0.5s;
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
                        // set image option to template
                        document.getElementById('imageOptionInput').value = 'template';
                        // Set active class
                        imageOptionButtons.forEach(btn => btn.classList.remove('active'));
                        document.querySelector('.image-option-button[data-option="template"]').classList.add('active');
                    });
                });

            });

            function openTemplateModal() {
                const templateModal = document.getElementById('templateModal');
                templateModal.style.display = 'block';
            }
        </script>
    </head>
    <body>
        <div class="container">
            <h1>ユーザーダッシュボード</h1>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message">
                    <p>リンクが正常に処理されました。</p>
                </div>
            <?php endif; ?>

            <!-- ログアウトボタン -->
            <form method="POST" style="text-align: right;">
                <input type="hidden" name="action" value="logout">
                <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                <button type="submit">ログアウト</button>
            </form>

            <!-- リンク生成フォーム -->
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                <input type="hidden" id="imageOptionInput" name="imageOption" required>
                <input type="hidden" id="selectedTemplateInput" name="selectedTemplate">

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

                <label>タイトル（必須）</label>
                <input type="text" name="title" required>

                <label>遷移先URL（必須）</label>
                <input type="url" name="target_url" required>

                <button type="submit" name="action" value="generate_link">リンクを生成</button>
            </form>

            <!-- 生成されたリンクのプレビュー -->
            <?php if (isset($_SESSION['user_id'])): ?>
                <h2>あなたの生成したリンク一覧</h2>
                <?php if (count($user_links) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>タイトル</th>
                                <th>リンク</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_links as $link): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($link['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><a href="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/' . $link['unique_id']; ?>" target="_blank"><?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/' . $link['unique_id']; ?></a></td>
                                    <td>
                                        <!-- 編集ボタン -->
                                        <button onclick="openEditForm('<?php echo $link['unique_id']; ?>', '<?php echo htmlspecialchars($link['title'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($link['target_url'], ENT_QUOTES, 'UTF-8'); ?>')">編集</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>まだリンクを生成していません。</p>
                <?php endif; ?>

                <!-- 編集フォーム -->
                <div id="editForm" class="edit-form" style="display:none;">
                    <h2>リンクの編集</h2>
                    <form method="POST">
                        <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                        <input type="hidden" name="action" value="edit_link">
                        <input type="hidden" name="unique_id" id="edit_unique_id">

                        <label>新しいタイトル</label>
                        <input type="text" name="new_title" id="edit_title" required>

                        <label>新しい遷移先URL</label>
                        <input type="url" name="new_target_url" id="edit_target_url" required>

                        <button type="submit">変更を保存</button>
                        <button type="button" onclick="closeEditForm()">キャンセル</button>
                    </form>
                </div>
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
                            <img src="temp/<?php echo $template; ?>" alt="<?php echo $template; ?>">
                            <input type="radio" name="templateRadio" value="<?php echo $template; ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <script>
            // 編集フォームの表示
            function openEditForm(unique_id, title, target_url) {
                document.getElementById('edit_unique_id').value = unique_id;
                document.getElementById('edit_title').value = title;
                document.getElementById('edit_target_url').value = target_url;
                document.getElementById('editForm').style.display = 'block';
            }

            function closeEditForm() {
                document.getElementById('editForm').style.display = 'none';
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ユーザーがログインしていない場合の処理
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ログイン - サムネイル付きリンク生成サービス</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* スタイルをここに記述 */
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
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            animation: fadeIn 1s ease-in-out;
        }
        .login-container {
            background-color: #1e1e1e;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            animation: scaleUp 0.5s ease-in-out;
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
            text-align: center;
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
</head>
<body>
    <div class="login-container">
        <h1>ログイン</h1>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
            <input type="hidden" name="action" value="login">
            <label>ユーザーID</label>
            <input type="text" name="username" required>
            <label>パスワード</label>
            <input type="password" name="password" required>
            <button type="submit">ログイン</button>
        </form>
    </div>
</body>
</html>
