<?php

use FastRaven\Bee;
use FastRaven\Types\ProjectFolderType;

?>

<!DOCTYPE html>
<html lang="<?= $template->getLang(); ?>">
    <head>
        <meta charset="UTF-8">
        <?= $template->getHtmlTitle(); ?>

        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">
        <meta name="format-detection" content="telephone=no">
        <?= $template->getHtmlCsrf(); ?>

        <link rel="stylesheet" href="/public/assets/css/base.css?v=<?= $template->getVersion(); ?>">

        <?= $template->getHtmlFavicon(); ?>
        <?= $template->getHtmlHead(); ?>
        <?= $template->getHtmlStyles(); ?>

        <?= $template->getHtmlLang(); ?>
        <script src="/public/assets/js/jquery.min.js?v=<?= $template->getVersion(); ?>"></script>
        <script src="/public/assets/js/lib.js?v=<?= $template->getVersion(); ?>"></script>
        <?= $template->getHtmlScripts(); ?>
    </head>
    <body>
        <?php
            $fragmentsPath = Bee::buildProjectPath(ProjectFolderType::SRC_WEB_TEMPLATES_FRAGMENTS);
            foreach ($template->getBeforeFragments() as $beforeFragment) {
                include $fragmentsPath . $beforeFragment;
            }
        ?>
        <main>
            <?php include Bee::buildProjectPath(ProjectFolderType::SRC_WEB_TEMPLATES_PAGES, $template->getFile()); ?>
        </main>
        <?php 
            foreach ($template->getAfterFragments() as $afterFragment) {
                include $fragmentsPath . $afterFragment;
            }
        ?>
    </body>
</html>
