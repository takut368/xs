<?php
session_start();

// リンクを格納するディレクトリ
$linksDir = __DIR__ . '/links/';

// ディレクトリが存在しない場合は作成
if (!is_dir($linksDir)) {
    mkdir($linksDir, 0777, true);
}

// フォームが送信された場合の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // リンクの種類を判別
    if (isset($_POST['url'])) {
        $originalUrl = trim($_POST['url']);
        // リダイレクト用のフォルダ名を生成（例: 1111111111111）
        $folderName = uniqid();
        $redirectFolder = $linksDir . $folderName;

        // フォルダを作成
        if (!mkdir($redirectFolder, 0777, true)) {
            die('フォルダの作成に失敗しました。');
        }

        // index.php を作成
        $indexPhpContent = "<?php\n";
        $indexPhpContent .= "header('Location: " . $originalUrl . "');\n";
        $indexPhpContent .= "exit;\n";

        file_put_contents($redirectFolder . '/index.php', $indexPhpContent);

        // 短縮URLを生成
        $shortUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/links/' . $folderName . '/';
        $_SESSION['short_url'] = $shortUrl;
    }
    // 他のフォームの処理（画像URL、ファイルアップロード、テンプレート選択など）は省略します
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>リンク生成ツール</title>
    <style>
        /* 基本スタイル */
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            text-align: center;
        }
        /* フォームスタイル */
        form {
            margin-top: 20px;
        }
        .input-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input[type="text"], input[type="file"] {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
        }
        button {
            padding: 10px 15px;
            background-color: #007BFF;
            color: #fff;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        /* ポップアップスタイル */
        #template_modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }
        #template_modal_content {
            background-color: #fff;
            margin: 10% auto;
            padding: 20px;
            width: 80%;
            max-width: 500px;
        }
        /* スマホ画面での間隔を統一 */
        @media (max-width: 600px) {
            .input-group {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>リンク生成ツール</h1>
        <?php if (isset($_SESSION['short_url'])): ?>
            <p>短縮URL: <a href="<?php echo $_SESSION['short_url']; ?>"><?php echo $_SESSION['short_url']; ?></a></p>
            <?php unset($_SESSION['short_url']); ?>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <div class="input-group">
                <label for="url">リダイレクト先のURLを入力してください:</label>
                <input type="text" id="url" name="url" required>
            </div>
            <!-- 他のフォーム要素（画像URL、ファイルアップロード、テンプレート選択など） -->
            <div class="input-group">
                <label for="image_url">画像URLを入力:</label>
                <input type="text" id="image_url" name="image_url">
            </div>
            <div class="input-group">
                <label for="image_file">画像ファイルをアップロード:</label>
                <input type="file" id="image_file" name="image_file">
            </div>
            <div class="input-group">
                <button type="button" id="select_template">テンプレートから選択</button>
            </div>
            <button type="submit">リンクを生成</button>
        </form>
    </div>

    <!-- テンプレート選択のポップアップ -->
    <div id="template_modal">
        <div id="template_modal_content">
            <h2>テンプレートから選択</h2>
            <!-- テンプレート一覧 -->
            <ul>
                <li>テンプレート1</li>
                <li>テンプレート2</li>
                <li>テンプレート3</li>
            </ul>
            <button id="close_modal">閉じる</button>
        </div>
    </div>

    <script>
        // 「テンプレートから選択」ボタンの動作を修正
        document.getElementById('select_template').addEventListener('click', function() {
            document.getElementById('template_modal').style.display = 'block';
        });
        document.getElementById('close_modal').addEventListener('click', function() {
            document.getElementById('template_modal').style.display = 'none';
        });
    </script>
</body>
</html>
