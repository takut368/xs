<?php
session_start();

// データファイルのパス
define('SEISEI_FILE', __DIR__ . '/data/seisei.json');

// 関数定義

/**
 * リンク情報を取得する関数
 */
function get_links() {
    if (!file_exists(SEISEI_FILE)) {
        file_put_contents(SEISEI_FILE, json_encode([], JSON_PRETTY_PRINT));
    }
    return json_decode(file_get_contents(SEISEI_FILE), true);
}

// セキュリティ対策: ドメインへのアクセス制限
if (!isset($_GET['id']) || $_GET['id'] !== '123') {
    // 画面を真っ白にする
    exit;
}

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $link_id = htmlspecialchars(trim($_GET['id']));
    $links = get_links();
    foreach ($links as $link) {
        if ($link['id'] === $link_id && $link['user_id'] === $_SESSION['user']['id']) {
            echo json_encode(['success' => true, 'link' => $link]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'message' => 'リンクが見つかりませんでした。']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'リンクIDが指定されていません。']);
exit;
?>
