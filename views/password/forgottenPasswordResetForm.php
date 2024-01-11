<form action="" method="POST">
    <?
    formHidden('mode', 'reset');
    formHidden('code');
    ?>

    <div class="form-grid">
        <label class="form-row transparent">
            <span>Password:</span>
            <input type="password" name="password" id="resetPassword" class="password" placeholder="Password" autocomplete="current-password" />
            <? if (inputError('password',false)) { ?>
                <div class="error">
                    <? inputError('password'); ?>
                </div>
            <? } ?>
        </label>
        <label class="form-row transparent">
            <span>Confirm Password:</span>
            <input type="password" name="confirmPassword" id="resetConfirmPassword" class="password" placeholder="Password" autocomplete="current-confirm-password" />
            <? if (inputError('confirmPassword',false)) { ?>
                <div class="error">
                    <? inputError('confirmPassword'); ?>
                </div>
            <? } ?>
        </label>

        <div>
            <input type="submit" name="reset" value="reset" />
        </div>
    </div>
</form>
