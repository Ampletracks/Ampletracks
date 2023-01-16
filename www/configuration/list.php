<?

$listSql = 'SELECT * FROM configuration ORDER BY name ASC';

function afterList(){
	echo "<ul>";
	if (defined('ACME_ACCOUNT_EMAIL') && ACME_ACCOUNT_EMAIL) {?>
		<li><a href="/acme/checkOrders.php">Check Let's Encrypt order status</a></li>
	<?}
	echo "</ul>";
}

$hideAddButton=true;
include( '../../lib/core/listPage.php' );
