<? include(VIEWS_DIR.'header.php'); ?>

<h1><?=cms('error header', 0, 'Error')?></h1>

<p><? echo $error; ?></p>

<?
	global $backHref;
	if (isset($backHref) && strlen($backHref)) { ?>
		<a href="<?=htmlspecialchars($backHref)?>" class="back button">Back</a>
<? } ?>

<? include(VIEWS_DIR.'footer.php'); ?>
