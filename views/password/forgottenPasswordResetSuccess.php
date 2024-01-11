<form action="/login.php" method="POST">
    <? formHidden('mode','login'); ?>
    <? formHidden('username', $email); ?>
    <? formHidden('password'); ?>

    <div class="form-grid">
        <label class="form-row transparent">
            <span>Password updated successfully</span>
        </label>
        <div>
            <input type="submit" name="send" value="Login" />
        </div>
    </div>
</form>
