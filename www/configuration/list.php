<?

$listSql = 'SELECT * FROM configuration WHERE 1=1 ORDER BY name ASC';

function afterList(){
	echo "<ul>";
	if (defined('ACME_ACCOUNT_EMAIL') && ACME_ACCOUNT_EMAIL) {?>
		<li><a href="/acme/checkOrders.php">Check Let's Encrypt order status</a></li>
	<?}
	echo "</ul>";
}

$hideAddButton=true;

function extraButtonsBefore(){
    if (defined('IOD_ROLE') && IOD_ROLE!=='master') return;
    ?>
        <a class="btn" href="../iodRequest/list.php">Instance On Demand</a>
    <?
}

include( '../../lib/core/listPage.php' );
