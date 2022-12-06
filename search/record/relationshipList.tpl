<TEMPLATE NAME="COMMON">
    <? global $numRelationships; ?>
</TEMPLATE>

<TEMPLATE NAME="HEADER">
    <? $numRelationships=0; ?>
</TEMPLATE>

<TEMPLATE NAME="LIST">
    <? $numRelationships++; ?>
    <div class="questionAndAnswer relationship list">
        <div class="question">
            This <? global $entityName; echo htmlspecialchars($entityName)?> <b>@@description@@</b>
        </div>
        <div class="answer">
            <a href="admin.php?id=@@recordId@@">the <b>@@recordType@@</b> identified as <b>@@name@@</b></a>
            <button class="delete btn small" data-relationshipid="@@id@@">Delete</button>
        </div>
    </div>
</TEMPLATE>

<TEMPLATE NAME="EMPTY">
    <div class="questionAndAnswer relationship empty">
        <p>
            <?=cms('No relationships defined',1,'There are not currently any relationships defined for this record'); ?>
        </p>
    </div>
</TEMPLATE>

<TEMPLATE NAME="FOOTER">
    <script>
        numRelationships = <?=(int)$numRelationships?>;

        $('span.relationshipCount').text(numRelationships);
    </script>
</TEMPLATE>
