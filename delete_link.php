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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $linkId = $_GET['id'] ?? '';

    if (empty($linkId)) {
        header('Location: index.php');
        exit();
    }

    $seisei = json_decode(file_get_contents('data/seisei.json'), true);
    if (isset($seisei[$linkId]) && $seisei[$linkId]['user'] === $username) {
        // リンクの削除
        unset($seisei[$linkId]);
        file_put_contents('data/seisei.json', json_encode($seisei, JSON_PRETTY_PRINT));

        // フォルダの削除
        $dirPath = $linkId;
        if (is_dir($dirPath)) {
            // ファイルを削除してからフォルダを削除
            $files = glob($dirPath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($dirPath);
        }

        // ログ記録
        $logs = json_decode(file_get_contents('data/logs.json'), true);
        $logs[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => $username,
            'action' => 'リンク削除: ' . $linkId
        ];
        file_put_contents('data/logs.json', json_encode($logs, JSON_PRETTY_PRINT));

        header('Location: index.php');
        exit();
    } else {
        header('Location: index.php');
        exit();
    }
} else {
    header('Location: index.php');
    exit();
}
?>
