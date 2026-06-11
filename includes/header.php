<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= APP_NAME ?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' . SCHOOL_NAME : SCHOOL_NAME ?></title>
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <?= isset($extraStyles) ? $extraStyles : '' ?>
</head>
<body>
<?php 
$notifCount = 0;
if (isset($_SESSION['user_id'])): 
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $notifCount = (int)$stmt->fetchColumn();
?>
<div class="d-flex" id="wrapper">
    <?php include 'sidebar.php'; ?>
    <div id="page-content-wrapper">
        <?php include 'navbar.php'; ?>
        <div class="container-fluid px-4 py-3">
<?php else: ?>
<div class="min-vh-100">
<?php endif; ?>
