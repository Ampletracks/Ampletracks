<form action="" method="POST">
    <? formHidden('mode','sendLink'); ?>

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
                <input type="text" name="email" placeholder="Email Address" />
            </label>
        <? } ?>

        <? if (inputError('login',false)) { ?>
            <div class="error">
                <? inputError('login'); ?>
            </div>
        <? } ?>

        <div>
            <input type="submit" name="send" value="Send" />
        </div>
    </div>
</form>
