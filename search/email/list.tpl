<TEMPLATE NAME="COMMON">
<?
    static $emailIds;
    if (!isset($emailIds)) $emailIds=array();
?>
</TEMPLATE>

<TEMPLATE NAME="HEADER">
<? global $templateFilter, $batchFilter, $WS; ?>
<table class="main data-table">
	<thead class="thead-gray">
	<tr>
		<th class="actions">
		    Actions
		</th>
		<th class="status">
			Status<br />
			<? formOptionbox('filter_email:status_eq',array('Any'=>'','New'=>'new','Held'=>'Held','Failed'=>'error','Sent'=>'sent')); ?>
		</th>
        <th class="template">
            Template<br>
            <? $templateFilter->display(); formPlaceholder('filter_emailTemplate:typeId_eq'); ?>
            <br>
        </th>
		<th class="recipients">
		    Recipients<br /> 
			<? formTextbox('filter_emailAddress:address_ct',20,255); ?>
		</th>
		<th class="priority">
		    Priority<br />
            <? formOptionbox('filter_email:priority_eq',['Any'=>'','Immediate'=>'immediate','High'=>'high','Medium'=>'medium','Low'=>'low']); ?>
		</th>
		<th class="sendAfter">
            Send after
            <? formTextbox('filter_email:sendAfter_on',10,10,null,'class="datepicker"'); ?>
            <br />
            Sent at
            <? formTextbox('filter_email:lastSendAttemptedAt_on',10,10,null,'class="datepicker"'); ?>
		</th>

	</tr>
	</thead>
</form>
	<tbody>
</TEMPLATE>

<TEMPLATE NAME="LIST">
<?
    global $lastRowData;
    $emailIds[] = $rowData['id'];

    if (!strpos('|held|new|',$rowData['status']) && is_array($lastRowData) && strpos('|held|new|',$lastRowData['status'])) {
        echo '<tr class="newVsSentEmailDivider"><td colspan="99"></td></tr>';
    }
?>
<tr>
<td class="actions">
    <a href="admin.php?id=@@id@@">Details</a>
    <? if (strpos('|new|error|held|',$rowData['status'])) { ?>
        <a deletePrompt="Are you sure you want to delete this email" href="admin.php?id=@@id@@&mode=xxdelete">Delete</a>
    <? } ?>
</td>
<td><?=ucfirst($rowData['status']);?></td>
<td>@@template@@</td>
<td><div id="recipients_@@id@@"></div></td>
<td>@@priority@@</td>
<td>
    <?
        if ($rowData['sendAfter']) {
            echo date('d/m/Y H:i',$rowData['sendAfter']);
        } else {
            echo '-';
        }
    ?><br />
    <?
        if ($rowData['status']=='sent') echo date('d/m/Y H:i',$rowData['lastSendAttemptedAt']);
        else echo 'n/a';
    ?>
</td>
</tr>
<? $lastRowData = $rowData; ?>
</TEMPLATE>

<TEMPLATE NAME="EMPTY">
<tr>
	<td align="center" colspan="999">
	<?=cms('No rows found');?>
	</td>
</tr>
</TEMPLATE>

<TEMPLATE NAME="FOOTER">
</tbody>
</table>
<?
    $recipients = new search('email/recipientList',array('
        SELECT
            emailId,
            CONCAT( emailRecipient.type,": ",emailAddress.email ) as recipients
        FROM
            emailRecipient
            INNER JOIN emailAddress ON emailRecipient.emailAddressId=emailAddress.id
        WHERE
            emailRecipient.emailId IN (?)
    ',$emailIds));
    $recipients->bundleArray(1);
    $recipients->display();
?>
</TEMPLATE>
