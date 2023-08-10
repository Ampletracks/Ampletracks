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
                    <h1><?=cms('Login: Login Header', 0, 'Login')?></h1>
                    <p><?=cms('Login: Intro')?></p>

                    <form action="" method="POST">
                        <? formHidden('mode','login'); ?>

                        <div class="form-grid">
                            <? if ($showCaptcha) { ?>
                                <div class="credentials captcha">
                                    <? formHidden('username',$_POST['username']); ?>
                                    <? formHidden('password',$_POST['password']); ?>
                                    <label class="form-row transparent">
                                        <div class="g-recaptcha" data-sitekey="<?=LOGIN_RECAPTCHA_SITE_KEY?>"></div>
                                    </label>
                                </div>
                            <? } else { ?>
                                <label class="form-row transparent">
                                    <span>Email Address:</span>
                                    <input type="text" name="username" id="loginUsername" placeholder="Email Address" autocomplete="username" />
                                </label>

                                <label class="form-row transparent">
                                    <span>Password:</span>
                                    <input type="password" name="password" id="loginPassword" class="password" placeholder="Password" autocomplete="current-password" />
                                </label>
                            <? } ?>

                            <? if (inputError('login',false)) { ?>
                                <div class="error">
                                    <? inputError('login'); ?>
                                </div>
                            <? } ?>


                            <label class="form-row checkbox transparent">
                                <input type="checkbox" name="persistLogin" id="persistLoginCheckbox">
                                <span>Keep me logged in for next 7 days</span>
                            </label>

                            <div>
                                <input type="submit" name="login" value="Login" />
                            </div>
                        </div>
                    </div>
                </form>

                <br/>

                <ul class="links-list">
                    <? /*
                    NOT IMPLEMENTED YED
                    <li><a href="#">Forgotten your password?</a></li>
                    */ ?>
                    <? if (defined('LOGIN_RECAPTCHA_SITE_KEY') && getConfig('New account request email')) { ?>
                        <li>
                            <a id="requestAccountButton" href="/user/requestAccount.php">
                                <?=cms('Request account link text',0,'Request your own account')?>
                            </a>
                        </li>
                    <? } ?>
                    <li>
                        <a id="scanLabelButton" href="#">
                            <?= getSVGIcon('scanLabel') ?>
                            Want to scan a label?
                        </a>
                    </li>
                </ul>
            </div>

            <div class="scanLabel" style="display: none">
                <h1><?=cms('Login: Scan Label Header',0,'Scan A Label')?></h1>

                <iframe class="scanQRCode" src="" ></iframe>
            </div>

            <script>
                // Focus on the username box if it is empty
                let usernameField = document.forms[0].username;
                if (!usernameField.value.length) usernameField.focus();
                // ... but if the username box is filled in, then focus on the password
                else document.forms[0].password.focus();

                $('a.passwordResetLink').on('click',function(){
                    let self = $(this);
                    let username = $('#loginUsername').val();
                    if (!username.length) return true;
                    self.attr('href',self.attr('href')+'&username='+encodeURIComponent(username));
                });

                $('#scanLabelButton').on('click',function(){
                    $(this).hide();
                    $('.scanLabel').show().find('iframe').attr('src','/scanQRCode.php');
                });
            </script>
        </main>
        <footer>
        </footer>
    </body>
</html>
