<?php
require_once __DIR__ . '/functions.php';

$pageTitle = $page_title ?? 'Rental Network';
$pageSlug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $pageTitle));
$pageSlug = trim($pageSlug, '-');
$styleVersion = (string) filemtime(__DIR__ . '/../assets/css/style.css');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($pageTitle); ?> | Rental Network</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php if (!empty($include_leaflet)) : ?>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo e(base_url('assets/css/style.css?v=' . $styleVersion)); ?>">
</head>
<body class="site-body page-<?php echo e($pageSlug ?: 'rental-network'); ?>">
<?php require __DIR__ . '/navbar.php'; ?>
<main class="site-main">
    <div class="container py-4">
        <?php display_flash(); ?>
