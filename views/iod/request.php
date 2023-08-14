<? include(VIEWS_DIR.'/header.php'); ?>
<h1><?=cms('Instance on demand request page header',0,'Test Site Signup');?></h1>
<p>
<?=cms('Instance on demand request page intro.',1,'Please fill out all of the fields below then click submit. We will then spin up a test site for you and email you the details - this usually takes less than 5 minutes.');?>
</p>
<? inputError('general'); ?>
<form method="POST">
<? formHidden('mode','request'); ?>
<div class="questionAndAnswer">
    <div class="question">First name:</div>
    <div class="answer"><? formTextbox('firstName', 50, 20); ?></div>
    <? inputError('firstName'); ?>
</div>
<div class="questionAndAnswer">
    <div class="question">Last name:</div>
    <div class="answer"><? formTextbox('lastName', 50, 20); ?></div>
    <? inputError('lastName'); ?>
</div>
<div class="questionAndAnswer">
    <div class="question">Email:</div>
    <div class="answer">
        <? formTextbox('email', 50); ?>
    </div>
    <? inputError('email'); ?>
</div>
<div class="questionAndAnswer">
    <div class="question">Password:</div>
    <div class="answer"><? formTextbox('password', -20,20,null,'autocomplete="new-password"'); ?></div>
    <? inputError('password'); ?>
    <div class="info">
        <?=cms('Instance on demand request password help',1,'This must be at least 10 characters long. You will use your email address and this password to log in to your new server.') ?>
    </div>
</div>

<? if (defined('LOGIN_RECAPTCHA_SITE_KEY')) { ?>
    <div class="g-recaptcha" data-sitekey="<?=htmlspecialchars(LOGIN_RECAPTCHA_SITE_KEY)?>"></div>
<? } ?>
<? inputError('captcha'); ?>
<ul class="btn-list bottom">
    <button class="save btn" name="submitButton" type="submit" value="Submit"><?=cms('Instance on demand request submit button',0,'Submit')?></button>
</ul>
</form>
<? include(VIEWS_DIR.'/footer.php'); ?>
