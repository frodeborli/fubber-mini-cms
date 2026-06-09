<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php $this->show('title', $siteName ?? 'My Site'); ?></title>
    <link rel="stylesheet" href="/style.css">
    <?php $this->show('head'); ?>
</head>
<body>
    <?php $this->show('body'); ?>
<?php $this->show('scripts'); ?>
</body>
</html>
