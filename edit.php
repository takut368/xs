<?php
// 設定
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ドキュメントルート取得
$base_path = realpath($_SERVER['DOCUMENT_ROOT']);

// GETパラメータ処理
$current_dir = isset($_GET['dir']) ? $_GET['dir'] : $base_path;
$current_dir = realpath($current_dir);
if (strpos($current_dir, $base_path) !== 0) {
    $current_dir = $base_path;
}

// ファイル内容取得用 (他の出力が行われる前に処理して即終了)
if (isset($_GET['getfile'])) {
    $gf = $_GET['getfile'];
    $gf_full = realpath($current_dir . DIRECTORY_SEPARATOR . $gf);
    if ($gf_full && strpos($gf_full, $current_dir) === 0 && is_file($gf_full)) {
        header('Content-Type: text/plain; charset=UTF-8');
        echo file_get_contents($gf_full);
        exit;
    } else {
        header('HTTP/1.1 404 Not Found');
        exit;
    }
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';

// AJAX用アクション処理
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $res = ['success' => false, 'message' => 'Unknown error'];
    $action = $_POST['action'];

    if ($action == 'delete' && isset($_POST['target'])) {
        $target = realpath($current_dir . DIRECTORY_SEPARATOR . $_POST['target']);
        if ($target && strpos($target, $current_dir) === 0) {
            if (is_dir($target)) {
                // ディレクトリ削除(再帰的)
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($it as $file) {
                    $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
                }
                rmdir($target);
                $res['success'] = true;
                $res['message'] = 'Directory deleted';
            } else {
                if (unlink($target)) {
                    $res['success'] = true;
                    $res['message'] = 'File deleted';
                } else {
                    $res['message'] = 'Failed to delete file';
                }
            }
        } else {
            $res['message'] = 'Invalid target';
        }
        echo json_encode($res);
        exit;
    } elseif ($action == 'rename' && isset($_POST['oldname']) && isset($_POST['newname'])) {
        $old = realpath($current_dir . DIRECTORY_SEPARATOR . $_POST['oldname']);
        $new = $current_dir . DIRECTORY_SEPARATOR . $_POST['newname'];
        if ($old && strpos($old, $current_dir) === 0 && !file_exists($new)) {
            if (rename($old, $new)) {
                $res['success'] = true;
                $res['message'] = 'Renamed successfully';
            } else {
                $res['message'] = 'Rename failed';
            }
        } else {
            $res['message'] = 'Invalid rename parameters';
        }
        echo json_encode($res);
        exit;
    } elseif ($action == 'mkdir' && isset($_POST['dirname'])) {
        $new_dir = $_POST['dirname'];
        if ($new_dir) {
            $target = $current_dir . DIRECTORY_SEPARATOR . $new_dir;
            if(!file_exists($target)) {
                if(mkdir($target, 0777, true)) {
                    $res['success'] = true;
                    $res['message'] = 'Directory created';
                } else {
                    $res['message'] = 'Failed to create directory';
                }
            } else {
                $res['message'] = 'Directory already exists';
            }
        } else {
            $res['message'] = 'No dirname provided';
        }
        echo json_encode($res);
        exit;
    } elseif ($action == 'touch' && isset($_POST['filename'])) {
        $new_file = $_POST['filename'];
        if ($new_file) {
            $target = $current_dir . DIRECTORY_SEPARATOR . $new_file;
            if(!file_exists($target)) {
                if(touch($target)) {
                    $res['success'] = true;
                    $res['message'] = 'File created';
                } else {
                    $res['message'] = 'Failed to create file';
                }
            } else {
                $res['message'] = 'File already exists';
            }
        } else {
            $res['message'] = 'No filename provided';
        }
        echo json_encode($res);
        exit;
    } elseif ($action == 'copy' && isset($_POST['source']) && isset($_POST['destdir']) && isset($_POST['destination'])) {
        $src = realpath($current_dir . DIRECTORY_SEPARATOR . $_POST['source']);
        $dest_dir = realpath($_POST['destdir']);
        $dst = $dest_dir . DIRECTORY_SEPARATOR . $_POST['destination'];
        if ($src && $dest_dir && strpos($src, $base_path) === 0 && strpos($dest_dir, $base_path) === 0) {
            if (is_file($src)) {
                if (copy($src, $dst)) {
                    $res['success'] = true;
                    $res['message'] = 'File copied';
                } else {
                    $res['message'] = 'Failed to copy file';
                }
            } elseif (is_dir($src)) {
                function copy_dir($src, $dst) {
                    if(!file_exists($dst)) {
                        mkdir($dst, 0777, true);
                    }
                    $dir = opendir($src);
                    while(($file = readdir($dir)) !== false) {
                        if($file == '.' || $file == '..') continue;
                        $srcfile = $src . DIRECTORY_SEPARATOR . $file;
                        $dstfile = $dst . DIRECTORY_SEPARATOR . $file;
                        if(is_dir($srcfile)) {
                            copy_dir($srcfile, $dstfile);
                        } else {
                            copy($srcfile, $dstfile);
                        }
                    }
                    closedir($dir);
                }
                copy_dir($src, $dst);
                $res['success'] = true;
                $res['message'] = 'Directory copied';
            }
        } else {
            $res['message'] = 'Invalid copy source/destination';
        }
        echo json_encode($res);
        exit;
    } elseif ($action == 'move' && isset($_POST['source']) && isset($_POST['destdir']) && isset($_POST['destination'])) {
        $src = realpath($current_dir . DIRECTORY_SEPARATOR . $_POST['source']);
        $dest_dir = realpath($_POST['destdir']);
        $dst = $dest_dir . DIRECTORY_SEPARATOR . $_POST['destination'];
        if ($src && $dest_dir && strpos($src, $base_path) === 0 && strpos($dest_dir, $base_path) === 0 && !file_exists($dst)) {
            if (rename($src, $dst)) {
                $res['success'] = true;
                $res['message'] = 'Moved successfully';
            } else {
                $res['message'] = 'Move failed';
            }
        } else {
            $res['message'] = 'Invalid move parameters';
        }
        echo json_encode($res);
        exit;
    } elseif ($action == 'save' && isset($_POST['editfile']) && isset($_POST['filecontent'])) {
        $file = realpath($current_dir . DIRECTORY_SEPARATOR . $_POST['editfile']);
        if ($file && strpos($file, $current_dir) === 0 && is_file($file)) {
            if(file_put_contents($file, $_POST['filecontent']) !== false) {
                $res['success'] = true;
                $res['message'] = 'File saved';
            } else {
                $res['message'] = 'Failed to save file';
            }
        } else {
            $res['message'] = 'Invalid file';
        }
        echo json_encode($res);
        exit;
    } elseif ($action == 'listdir' && isset($_POST['dirpath'])) {
        // AJAXでディレクトリ一覧取得用
        $dirpath = realpath($_POST['dirpath']);
        if($dirpath && strpos($dirpath, $base_path) === 0 && is_dir($dirpath)) {
            $items = scandir($dirpath);
            $dirs = [];
            $parent = null;
            if($dirpath != $base_path) {
                $parent = dirname($dirpath);
            }
            foreach($items as $item) {
                if($item == '.' || $item == '..') continue;
                $full = $dirpath . DIRECTORY_SEPARATOR . $item;
                if(is_dir($full)) {
                    $dirs[] = $item;
                }
            }
            $res['success'] = true;
            $res['parent'] = $parent;
            $res['current'] = $dirpath;
            $res['dirs'] = $dirs;
        } else {
            $res['message'] = 'Invalid directory';
        }
        echo json_encode($res);
        exit;
    }

    echo json_encode($res);
    exit;
}

// ファイル一覧取得
$files = scandir($current_dir);
// 親ディレクトリへのリンク
$parent_dir = ($current_dir != $base_path) ? dirname($current_dir) : null;

// 検索フィルタ
if ($search !== '') {
    $files = array_filter($files, function($f) use($search) {
        if ($f == '.' || $f == '..') return true;
        return (stripos($f, $search) !== false);
    });
}

// ソート
usort($files, function($a, $b) use ($current_dir, $sort) {
    if ($a == '.' || $a == '..') return -1;
    if ($b == '.' || $b == '..') return 1;
    
    $a_path = $current_dir . DIRECTORY_SEPARATOR . $a;
    $b_path = $current_dir . DIRECTORY_SEPARATOR . $b;
    $a_is_dir = is_dir($a_path);
    $b_is_dir = is_dir($b_path);

    $get_key = function($file, $path, $is_dir, $sort) {
        switch($sort) {
            case 'type':
                return $is_dir ? '0' : '1';
            case 'size':
                return $is_dir ? -1 : filesize($path);
            case 'name':
            default:
                return strtolower($file);
        }
    };
    $a_key = $get_key($a, $a_path, $a_is_dir, $sort);
    $b_key = $get_key($b, $b_path, $b_is_dir, $sort);
    
    if ($a_key == $b_key) return 0;
    return ($a_key < $b_key) ? -1 : 1;
});

$image_ext = ['jpg','jpeg','png','gif','webp'];
$video_ext = ['mp4','webm','ogg'];

?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>File Explorer</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
<style>
    body {
        background-color: #f0f0f0;
    }
    .explorer-container {
        margin: 20px;
        background-color: #ffffff;
        border-radius: 5px;
        padding: 20px;
    }
    .breadcrumb {
        background: none;
        padding: 0;
        margin-bottom: 10px;
    }
    .toolbar {
        margin-bottom: 10px;
    }
    .file-table th, .file-table td {
        vertical-align: middle;
        white-space: nowrap;
    }
    .folder-icon {
        color: #007bff;
    }
    .file-icon {
        color: #555;
    }
    .table-responsive {
        margin-top: 10px;
    }
    .search-form {
        float: right;
    }
    @media (max-width: 767px) {
        .search-form {
            float: none;
            margin-top: 10px;
        }
        /* スマホ時 種類とサイズ非表示 */
        .type-col, .size-col {
            display: none !important;
        }
    }

    /* エディットモーダルを90%表示 */
    #editModal .modal-dialog {
        max-width: 90% !important;
        width: 90%;
        height: 90%;
        max-height: 90%;
    }
    #editModal .modal-content {
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    #editModal .modal-body {
        flex: 1;
        overflow: auto;
    }
    #editModal .line-count {
        position: absolute;
        right: 80px; /* ボタンと被らないよう */
        top: 20px;
        font-size: 0.9rem;
        color: #666;
    }
</style>
</head>
<body>
<div class="container-fluid">
<div class="explorer-container">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <h2 class="mb-0"><i class="fas fa-folder-open"></i> ファイルエクスプローラ</h2>
        <form class="search-form" method="get">
            <input type="hidden" name="dir" value="<?php echo htmlspecialchars($current_dir); ?>">
            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
            <div class="input-group">
              <input type="text" class="form-control" placeholder="検索..." name="search" value="<?php echo htmlspecialchars($search); ?>">
              <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
            </div>
        </form>
    </div>
    <nav aria-label="breadcrumb" class="mt-3">
      <ol class="breadcrumb">
        <?php
            echo '<li class="breadcrumb-item"><a href="?dir=' . urlencode($base_path) . '&sort=' . urlencode($sort) . '&search=' . urlencode($search) . '">root</a></li>';
            $parts = explode(DIRECTORY_SEPARATOR, trim(str_replace($base_path, '', $current_dir), DIRECTORY_SEPARATOR));
            $build_path = $base_path;
            foreach ($parts as $part) {
                $build_path .= DIRECTORY_SEPARATOR . $part;
                echo '<li class="breadcrumb-item"><a href="?dir=' . urlencode($build_path) . '&sort=' . urlencode($sort) . '&search=' . urlencode($search) . '">' . htmlspecialchars($part) . '</a></li>';
            }
        ?>
      </ol>
    </nav>
    
    <div class="toolbar">
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#mkdirModal"><i class="fas fa-folder-plus"></i> フォルダ作成</button>
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#touchModal"><i class="fas fa-file-medical"></i> ファイル作成</button>
    </div>
    
    <div class="table-responsive">
    <table class="table table-striped table-hover file-table">
        <thead>
            <tr>
                <th><a href="?dir=<?php echo urlencode($current_dir); ?>&search=<?php echo urlencode($search); ?>&sort=name">名前</a></th>
                <th class="type-col"><a href="?dir=<?php echo urlencode($current_dir); ?>&search=<?php echo urlencode($search); ?>&sort=type">種類</a></th>
                <th class="size-col"><a href="?dir=<?php echo urlencode($current_dir); ?>&search=<?php echo urlencode($search); ?>&sort=size">サイズ</a></th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($parent_dir): ?>
            <tr>
                <td><a href="?dir=<?php echo urlencode($parent_dir); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>"><i class="fas fa-level-up-alt"></i> ..</a></td>
                <td class="type-col">Directory</td>
                <td class="size-col">-</td>
                <td></td>
            </tr>
            <?php endif; ?>
            <?php
            foreach ($files as $f) {
                if ($f == '.' || $f == '..') {
                    continue;
                }
                $full_path = $current_dir . DIRECTORY_SEPARATOR . $f;
                $is_dir = is_dir($full_path);
                $icon = $is_dir ? '<i class="fas fa-folder folder-icon"></i>' : '<i class="fas fa-file file-icon"></i>';
                $type = $is_dir ? "Directory" : "File";
                $size = $is_dir ? '-' : filesize($full_path) . ' bytes';
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                
                echo '<tr data-name="'.htmlspecialchars($f).'">';
                echo '<td>';
                if ($is_dir) {
                    echo '<a href="?dir=' . urlencode($full_path) . '&search=' . urlencode($search) . '&sort=' . urlencode($sort) . '">' . $icon . ' ' . htmlspecialchars($f) . '</a>';
                } else {
                    echo $icon . ' ' . htmlspecialchars($f);
                }
                echo '</td>';
                echo '<td class="type-col">' . $type . '</td>';
                echo '<td class="size-col">' . $size . '</td>';
                echo '<td>';

                // 編集ボタン(フォルダはdisabled)
                if ($is_dir) {
                    echo '<button class="btn btn-sm btn-outline-success" disabled><i class="fas fa-edit"></i> 編集</button> ';
                } else {
                    echo '<button class="btn btn-sm btn-outline-success edit-btn" data-file="' . htmlspecialchars($f) . '"><i class="fas fa-edit"></i> 編集</button> ';
                }

                // 画像/動画閲覧ボタン
                if (!$is_dir && (in_array($ext, $image_ext) || in_array($ext, $video_ext))) {
                    echo '<button class="btn btn-sm btn-outline-primary view-btn" data-file="' . htmlspecialchars($f) . '"><i class="fas fa-eye"></i> 閲覧</button> ';
                }

                // リネーム
                echo '<button class="btn btn-sm btn-outline-warning rename-btn" data-old="' . htmlspecialchars($f) . '"><i class="fas fa-i-cursor"></i> リネーム</button> ';

                // コピー
                echo '<button class="btn btn-sm btn-outline-info copy-btn" data-source="' . htmlspecialchars($f) . '"><i class="fas fa-copy"></i> コピー</button> ';

                // 移動
                echo '<button class="btn btn-sm btn-outline-info move-btn" data-source="' . htmlspecialchars($f) . '"><i class="fas fa-arrows-alt"></i> 移動</button> ';

                // 削除
                echo '<button class="btn btn-sm btn-outline-danger delete-btn" data-target="' . htmlspecialchars($f) . '"><i class="fas fa-trash"></i> 削除</button>';

                echo '</td>';
                echo '</tr>';
            }
            ?>
        </tbody>
    </table>
    </div>
</div>
</div>

<!-- Modals -->
<!-- フォルダ作成モーダル -->
<div class="modal fade" id="mkdirModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="mkdirForm">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">フォルダ作成</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
          </div>
          <div class="modal-body">
            <label class="form-label">新規フォルダ名</label>
            <input type="text" name="dirname" class="form-control" required>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">作成</button>
          </div>
        </div>
    </form>
  </div>
</div>

<!-- ファイル作成モーダル -->
<div class="modal fade" id="touchModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="touchForm">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">ファイル作成</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
          </div>
          <div class="modal-body">
            <label class="form-label">新規ファイル名</label>
            <input type="text" name="filename" class="form-control" required>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">作成</button>
          </div>
        </div>
    </form>
  </div>
</div>

<!-- 削除モーダル -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="deleteForm">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">削除確認</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
          </div>
          <div class="modal-body">
            <p><span id="delete-target"></span> を削除しますか？</p>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-danger">削除</button>
          </div>
        </div>
    </form>
  </div>
</div>

<!-- リネームモーダル -->
<div class="modal fade" id="renameModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="renameForm">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">リネーム</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="oldname">
            <label class="form-label">新しい名前</label>
            <input type="text" name="newname" class="form-control" required>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-warning">リネーム</button>
          </div>
        </div>
    </form>
  </div>
</div>

<!-- コピー先選択モーダル -->
<div class="modal fade" id="copyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form id="copyForm">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">コピー先を選択</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="source">
            <div class="mb-3">
                <label class="form-label">コピー先ディレクトリを選択してください：</label>
                <div id="copy-dir-browser" class="border p-2" style="height:300px;overflow:auto;"></div>
            </div>
            <label class="form-label">コピー後の名前</label>
            <input type="text" name="destination" class="form-control" required>
            <input type="hidden" name="destdir">
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-info">コピー</button>
          </div>
        </div>
    </form>
  </div>
</div>

<!-- 移動先選択モーダル -->
<div class="modal fade" id="moveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form id="moveForm">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">移動先を選択</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="source">
            <div class="mb-3">
                <label class="form-label">移動先ディレクトリを選択してください：</label>
                <div id="move-dir-browser" class="border p-2" style="height:300px;overflow:auto;"></div>
            </div>
            <label class="form-label">移動後の名前</label>
            <input type="text" name="destination" class="form-control" required>
            <input type="hidden" name="destdir">
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-info">移動</button>
          </div>
        </div>
    </form>
  </div>
</div>

<!-- 編集モーダル -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <form id="editForm" style="height:100%;">
        <div class="modal-content" style="height:100%;">
          <div class="modal-header" style="position:relative;">
            <h5 class="modal-title">ファイル編集: <span id="editing-filename"></span></h5>
            <span class="line-count"></span>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
          </div>
          <div class="modal-body p-0">
            <input type="hidden" name="editfile">
            <textarea name="filecontent" class="form-control" style="width:100%;height:100%;border:0;"></textarea>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-success" disabled>保存</button>
          </div>
        </div>
    </form>
  </div>
</div>

<!-- 閲覧モーダル -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">閲覧</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
        </div>
        <div class="modal-body" id="viewContent">
          <!-- 画像または動画が表示される -->
        </div>
      </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var currentDir = <?php echo json_encode($current_dir); ?>;
    var basePath = <?php echo json_encode($base_path); ?>;

    function ajaxAction(data, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.href);
        xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (xhr.status == 200) {
                var res = JSON.parse(xhr.responseText);
                callback(res);
            } else {
                callback({success:false,message:'Server error'});
            }
        };
        xhr.send(Object.keys(data).map(function(k){
            return encodeURIComponent(k)+'='+encodeURIComponent(data[k]);
        }).join('&'));
    }

    // 削除
    var deleteModal = document.getElementById('deleteModal');
    var deleteForm = document.getElementById('deleteForm');
    var deleteTargetName = '';
    deleteModal.addEventListener('show.bs.modal', function (event) {
      var button = event.relatedTarget
      deleteTargetName = button.getAttribute('data-target')
      deleteModal.querySelector('#delete-target').textContent = deleteTargetName
    })
    deleteForm.addEventListener('submit', function(e) {
        e.preventDefault();
        ajaxAction({action:'delete',target:deleteTargetName}, function(res){
            if(res.success) {
                var row = document.querySelector('tr[data-name="'+CSS.escape(deleteTargetName)+'"]');
                if(row) row.remove();
                var modal = bootstrap.Modal.getInstance(deleteModal);
                modal.hide();
            } else {
                alert(res.message);
            }
        });
    });

    // リネーム
    var renameModal = document.getElementById('renameModal');
    var renameForm = document.getElementById('renameForm');
    var renameOldInput = renameForm.querySelector('input[name="oldname"]');
    renameModal.addEventListener('show.bs.modal', function (event) {
      var button = event.relatedTarget
      var oldname = button.getAttribute('data-old')
      renameOldInput.value = oldname
    });
    renameForm.addEventListener('submit', function(e){
        e.preventDefault();
        var oldname = renameForm.oldname.value;
        var newname = renameForm.newname.value;
        ajaxAction({action:'rename',oldname:oldname,newname:newname},function(res){
            if(res.success) {
                location.reload();
            } else {
                alert(res.message);
            }
        });
    });

    // フォルダ作成
    var mkdirForm = document.getElementById('mkdirForm');
    mkdirForm.addEventListener('submit', function(e){
        e.preventDefault();
        var dirname = mkdirForm.dirname.value;
        ajaxAction({action:'mkdir',dirname:dirname},function(res){
            if(res.success) {
                location.reload();
            } else {
                alert(res.message);
            }
        });
    });

    // ファイル作成
    var touchForm = document.getElementById('touchForm');
    touchForm.addEventListener('submit', function(e){
        e.preventDefault();
        var filename = touchForm.filename.value;
        ajaxAction({action:'touch',filename:filename},function(res){
            if(res.success) {
                location.reload();
            } else {
                alert(res.message);
            }
        });
    });

    // ディレクトリナビゲーション関数
    function loadDirList(targetElem, dirPath) {
        ajaxAction({action:'listdir',dirpath:dirPath}, function(res){
            if(res.success) {
                var html = '';
                if(res.parent !== null) {
                    html += '<div><button type="button" class="btn btn-sm btn-outline-secondary dir-up" data-parent="'+res.parent+'"><i class="fas fa-level-up-alt"></i> ..</button></div>';
                }
                res.dirs.forEach(function(d){
                    var full = res.current + '/' + d;
                    html += '<div><button type="button" class="btn btn-sm btn-outline-primary dir-into" data-dir="'+full+'"><i class="fas fa-folder"></i> '+d+'</button></div>';
                });
                if(res.dirs.length == 0 && res.parent == null) {
                    html += '<div>フォルダがありません</div>';
                }
                targetElem.innerHTML = html;
            } else {
                targetElem.innerHTML = '<div class="text-danger">'+res.message+'</div>';
            }
        });
    }

    // ディレクトリ選択(コピー)
    var copyModal = document.getElementById('copyModal');
    var copyForm = document.getElementById('copyForm');
    var copySourceInput = copyForm.querySelector('input[name="source"]');
    var copyDirBrowser = document.getElementById('copy-dir-browser');
    var copyDestDirInput = copyForm.querySelector('input[name="destdir"]');

    copyModal.addEventListener('show.bs.modal', function (event) {
      var button = event.relatedTarget
      var source = button.getAttribute('data-source')
      copySourceInput.value = source;
      loadDirList(copyDirBrowser, basePath);
      copyDestDirInput.value = basePath;
    });

    copyDirBrowser.addEventListener('click', function(e){
        var btn = e.target.closest('button');
        if(!btn) return;
        if(btn.classList.contains('dir-up')) {
            var p = btn.getAttribute('data-parent');
            loadDirList(copyDirBrowser, p);
            copyDestDirInput.value = p;
        } else if(btn.classList.contains('dir-into')) {
            var d = btn.getAttribute('data-dir');
            loadDirList(copyDirBrowser, d);
            copyDestDirInput.value = d;
        }
    });

    copyForm.addEventListener('submit', function(e){
        e.preventDefault();
        var source = copyForm.source.value;
        var destination = copyForm.destination.value;
        var destdir = copyForm.destdir.value;
        ajaxAction({action:'copy',source:source,destdir:destdir,destination:destination},function(res){
            if(res.success) {
                location.reload();
            } else {
                alert(res.message);
            }
        });
    });

    // ディレクトリ選択(移動)
    var moveModal = document.getElementById('moveModal');
    var moveForm = document.getElementById('moveForm');
    var moveSourceInput = moveForm.querySelector('input[name="source"]');
    var moveDirBrowser = document.getElementById('move-dir-browser');
    var moveDestDirInput = moveForm.querySelector('input[name="destdir"]');

    moveModal.addEventListener('show.bs.modal', function (event) {
      var button = event.relatedTarget
      var source = button.getAttribute('data-source')
      moveSourceInput.value = source;
      loadDirList(moveDirBrowser, basePath);
      moveDestDirInput.value = basePath;
    });
    moveDirBrowser.addEventListener('click', function(e){
        var btn = e.target.closest('button');
        if(!btn) return;
        if(btn.classList.contains('dir-up')) {
            var p = btn.getAttribute('data-parent');
            loadDirList(moveDirBrowser, p);
            moveDestDirInput.value = p;
        } else if(btn.classList.contains('dir-into')) {
            var d = btn.getAttribute('data-dir');
            loadDirList(moveDirBrowser, d);
            moveDestDirInput.value = d;
        }
    });

    moveForm.addEventListener('submit', function(e){
        e.preventDefault();
        var source = moveForm.source.value;
        var destination = moveForm.destination.value;
        var destdir = moveForm.destdir.value;
        ajaxAction({action:'move',source:source,destdir:destdir,destination:destination},function(res){
            if(res.success) {
                location.reload();
            } else {
                alert(res.message);
            }
        });
    });

    // 編集
    var editModal = document.getElementById('editModal');
    var editForm = document.getElementById('editForm');
    var editFileInput = editForm.querySelector('input[name="editfile"]');
    var editTextarea = editForm.querySelector('textarea[name="filecontent"]');
    var editSaveButton = editForm.querySelector('button[type="submit"]');
    var lineCountDisplay = editModal.querySelector('.line-count');
    var editingFilenameSpan = document.getElementById('editing-filename');
    var originalContent = '';

    function updateLineCount() {
        var lines = editTextarea.value.split('\n').length;
        lineCountDisplay.textContent = lines + '行';
    }
    function checkChanges() {
        if (editTextarea.value === originalContent) {
            editSaveButton.disabled = true;
        } else {
            editSaveButton.disabled = false;
        }
        updateLineCount();
    }

    document.querySelectorAll('.edit-btn').forEach(function(btn) {
        btn.addEventListener('click', function(){
            var file = btn.getAttribute('data-file');
            editingFilenameSpan.textContent = file;
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '?dir='+encodeURIComponent(currentDir)+'&getfile=' + encodeURIComponent(file));
            xhr.onload = function() {
                if (xhr.status == 200) {
                    editTextarea.value = xhr.responseText;
                    editFileInput.value = file;
                    originalContent = xhr.responseText;
                    checkChanges();
                    var modal = new bootstrap.Modal(editModal);
                    modal.show();
                } else {
                    alert('ファイル読み込みエラー');
                }
            }
            xhr.send();
        });
    });

    editTextarea.addEventListener('input', checkChanges);

    editForm.addEventListener('submit', function(e){
        e.preventDefault();
        ajaxAction({action:'save',editfile:editFileInput.value,filecontent:editTextarea.value},function(res){
            if(res.success) {
                var modal = bootstrap.Modal.getInstance(editModal);
                modal.hide();
            } else {
                alert(res.message);
            }
        });
    });

    // 閲覧(画像/動画)
    var viewModal = document.getElementById('viewModal');
    var viewContent = document.getElementById('viewContent');
    document.querySelectorAll('.view-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var file = btn.getAttribute('data-file');
            var ext = file.split('.').pop().toLowerCase();
            var isImage = <?php echo json_encode($image_ext); ?>.includes(ext);
            var isVideo = <?php echo json_encode($video_ext); ?>.includes(ext);
            viewContent.innerHTML = '';
            var rel = currentDir.replace(<?php echo json_encode($_SERVER['DOCUMENT_ROOT']); ?>,'');
            if(!rel.startsWith('/')) rel = '/'+rel;
            var src = rel+'/'+file;
            var modal = new bootstrap.Modal(viewModal);
            if(isImage) {
                viewContent.innerHTML = '<img src="'+src+'" class="img-fluid" alt="'+file+'">';
            } else if(isVideo) {
                viewContent.innerHTML = '<video src="'+src+'" class="img-fluid" controls></video>';
            }
            modal.show();
        });
    });

});
</script>
</body>
</html>
