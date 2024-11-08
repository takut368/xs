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

// リンク削除処理
unset($seiseiData[$currentUser][$linkId]);
file_put_contents($seiseiFile, json_encode($seiseiData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// リンクフォルダの削除
$dirPath = $baseDir . '/../' . $linkId;
if (is_dir($dirPath)) {
    // index.phpを削除
    if (file_exists($dirPath . '/index.php')) {
        unlink($dirPath . '/index.php');
    }
    // ディレクトリの削除
    rmdir($dirPath);
}

// ログの記録
$logs = json_decode(file_get_contents($logsFile), true);
$logs[] = [
    "user" => $currentUser,
    "action" => "deleted_link",
    "link_id" => $linkId,
    "timestamp" => date("Y-m-d H:i:s")
];
file_put_contents($logsFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

header("Location: index.php");
exit();
?>
