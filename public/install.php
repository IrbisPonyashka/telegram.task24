<?php

require_once __DIR__ . '../src/crest.php';

$result = CRest::installApp();

if (!$result['rest_only']) { ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Страница установки приложения</title>
        <?php
            $random = rand();
        ?>
        <script src="//api.bitrix24.com/api/v1/"></script>
    </head>
    <body>
    <script>
        BX24.init(function () {
            BX24.installFinish();
        });
    </script>

    </body>
    </html>

<?php } else {
    ?>
    <?php echo '<pre>';
    print_r($result);
    echo '</pre>'; ?>
<?php }