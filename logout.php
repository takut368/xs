<?php
session_start();
session_unset();
session_destroy();

// クッキーも削除
if (isset($_COOKIE['username'])) {
    setcookie('username', '', time() - 3600, "/");
}

header('Location: index.php');
exit();
?>
