<TEMPLATE NAME="HEADER">
</TEMPLATE>

<TEMPLATE NAME="LIST">
<div moveTo="#recipients_@@emailId@@">
    <?
        foreach ($rowData['bundle'] as &$recipient) { $recipient = ucfirst($recipient); }
        echo implode('<br />',$rowData['bundle']);
    ?>
</div>
</TEMPLATE>

<TEMPLATE NAME="EMPTY">
</TEMPLATE>

<TEMPLATE NAME="FOOTER">
</TEMPLATE>
