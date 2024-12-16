<?

include('../lib/core/startup.php');

// We set the USER_ID to zero here so that the header thinks the user is not logged in
// this causes the page to render without all the usual menus
$USER_ID=0;

include(VIEWS_DIR.'/header.php');
?>
<h1><?=cms('Login Warning: header',0)?></h1>
<p><?=cms('Login Warning: body',1)?></p>

<a href="/record/list.php" class="btn">Continue</a>

<?
include(VIEWS_DIR.'/footer.php');
