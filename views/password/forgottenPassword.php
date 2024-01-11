<?

global $showCaptcha;
if (!isset($showCaptcha)) $showCaptcha=false;

$cobrandingLogoUrl = getConfig('Cobranding logo url');

?>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Ampletracks</title>

        <? globalHeaderMarkup(); ?>
        <link rel="stylesheet" href="https://use.typekit.net/crp4ibc.css">
        <link rel="stylesheet" type="text/css" href="/stylesheets/main.css">
        <link rel="stylesheet" type="text/css" href="/stylesheets/login.css">
        <script src="/javascript/jquery.min.js"></script>
        <? if ($showCaptcha) { ?>
            <script src='https://www.google.com/recaptcha/api.js'></script>
        <? } ?>
    </head>
    <body class="login">
        <header class="site-header">
            <div class="container body-pad">
                <div class="top-row">
                    <ul class="logos">
                        <? if (!empty($cobrandingLogoUrl)) { ?>
                            <li><a href="/"><img src="<?=htmlspecialchars($cobrandingLogoUrl)?>" alt=""></a></li>
                        <? } ?>
                        <li><a href="/"><img src="/images/ampletracks-logo.svg" alt="Ampletracks logo"></a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main>
            <div class="container body-pad">
                <div class="login">
                    <h1><?=cms('Forgotten Password: Header', 0, 'Forgotten Password')?></h1>
                    <p>
                        <?
                        if($error) {
                            cms("Forgotten Password: $error");
                        } else {
                            cms('Forgotten Password: Intro');
                        }
                        ?>
                    </p>

                    <?
                    if($show == 'resetSuccess') {
                        include(VIEWS_DIR.'/password/forgottenPasswordResetSuccess.php');
                    } else if($show == 'resetForm') {
                        include(VIEWS_DIR.'/password/forgottenPasswordResetForm.php');
                    } else if($show == 'linkSent') {
                        include(VIEWS_DIR.'/password/forgottenPasswordLinkSent.php');
                    } else {
                        include(VIEWS_DIR.'/password/forgottenPasswordSend.php');
                    }
                    ?>
                </div>

                <br/>

                <ul class="links-list">
                    <li><a href="/login.php">Login</a></li>
                </ul>
            </div>
        </main>
        <footer>
        </footer>
    </body>
</html>
