<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo $metaDescription ?? 'DesiVastra - Premium luxury fashion, watches, jewelry, accessories and perfumes.'; ?>">
    <title><?php echo $pageTitle ?? 'DesiVastra - Luxury Fashion & Accessories'; ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="assets/css/global.css?v=<?php echo time(); ?>">
    <?php if (isset($extraCSS) && is_array($extraCSS)): ?>
        <?php foreach ($extraCSS as $css): ?>
            <link rel="stylesheet" href="assets/css/<?php echo $css; ?>?v=<?php echo time(); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <link rel="icon" href="favicon.svg" type="image/svg+xml">

    <script>
        window.DesiVastraConfig = {
            siteUrl: '<?php echo SITE_URL; ?>'
        };
    </script>
</head>
<body>
    <div class="app-container">