<?php
session_start();

// CSRFトークンチェック
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['token'], $_POST['token'])) {
        $response = ['error' => '不正なリクエストです。'];
        echo json_encode($response);
        exit;
    }

    $mainImageURL = filter_input(INPUT_POST, 'mainImageURL', FILTER_SANITIZE_URL);
    $editTemplate = $_POST['editTemplate'] ?? '';

    if (empty($mainImageURL) || !filter_var($mainImageURL, FILTER_VALIDATE_URL)) {
        $response = ['error' => '有効な画像URLを入力してください。'];
        echo json_encode($response);
        exit;
    }

    // メイン画像の取得
    $mainImageData = file_get_contents_curl($mainImageURL);
    if ($mainImageData === false) {
        $response = ['error' => '画像を取得できませんでした。'];
        echo json_encode($response);
        exit;
    }

    // 編集テンプレート画像の取得
    $editTemplates = ['saisei_button.png'];
    if (!in_array($editTemplate, $editTemplates)) {
        $response = ['error' => '有効な編集テンプレートを選択してください。'];
        echo json_encode($response);
        exit;
    }
    $editImageData = file_get_contents('temp/' . $editTemplate);

    // 画像編集処理
    $mainImage = imagecreatefromstring($mainImageData);
    $editImage = imagecreatefromstring($editImageData);

    // メイン画像のサイズ取得
    $mainWidth = imagesx($mainImage);
    $mainHeight = imagesy($mainImage);

    // 編集テンプレートをメイン画像の中央に配置
    $editWidth = imagesx($editImage);
    $editHeight = imagesy($editImage);

    $destX = ($mainWidth - $editWidth) / 2;
    $destY = ($mainHeight - $editHeight) / 2;

    imagecopy($mainImage, $editImage, $destX, $destY, 0, 0, $editWidth, $editHeight);

    // 画像の保存
    $editedImageName = 'edited_' . uniqid() . '.png';
    $editedImagePath = 'uploads/' . $editedImageName;
    imagepng($mainImage, $editedImagePath);

    // メモリ解放
    imagedestroy($mainImage);
    imagedestroy($editImage);

    // 編集後の画像URLを返却
    $editedImageURL = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $editedImagePath;
    $response = ['editedImageURL' => $editedImageURL];
    echo json_encode($response);
    exit;
}

// cURL関数
function file_get_contents_curl($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}
?>
