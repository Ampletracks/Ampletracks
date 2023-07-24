<? include(VIEWS_DIR.'/header.php'); ?>
<h1><?=cms('Request account page header',0,'New Account Request');?></h1>
<p>
<?=cms('Request account page intro.',1,'Please fill out all of the fields below then click submit. Your request for access will then be assessed by a member of the team');?>
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
    <div class="question">Mobile no.:</div>
    <div class="answer"><? formTextbox('mobile', 20,20); ?></div>
    <? inputError('mobile'); ?>
</div>
<div class="questionAndAnswer">
    <div class="question">Password:</div>
    <div class="answer"><? formTextbox('password', -20,20,null,'autocomplete="new-password"'); ?></div>
    <? inputError('password'); ?>
    <div class="info">
        <?=cms('Request account password help',1,'This field is optional. This must be at least 10 characters long. If you supply a password here and your access request is granted then the you will use your email address and this password to log in to your account. N.B. This password is encrypted and is not revealed to the person reviewing your request.') ?>
    </div>
</div>
<div class="questionAndAnswer">
    <div class="question">Supporting statement:</div>
    <div class="answer"><? formTextarea('supportingStatement',80,2); ?></div>
    <? inputError('supportingStatement'); ?>
    <div class="info">
        <?=cms('Request account supporting statement help',1,'Please provide details of why you should have access.') ?>
    </div>
</div>

<? if (defined('LOGIN_RECAPTCHA_SITE_KEY')) { ?>
    <div class="g-recaptcha" data-sitekey="<?=htmlspecialchars(LOGIN_RECAPTCHA_SITE_KEY)?>"></div>
<? } ?>
<? inputError('captcha'); ?>
<ul class="btn-list bottom">
    <button class="save btn" name="submitButton" type="submit" value="Submit"><?=cms('Request account submit button',0,'Submit')?></button>
</ul>
</form>
<? include(VIEWS_DIR.'/footer.php'); ?>
