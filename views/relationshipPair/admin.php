<table class="relationship">
    <tr class="from recordType">
        <td colspan="6" style="text-align: center">
            <? $recordTypeSelect->redisplay('fromRecordTypeId'); formPlaceholder('fromRecordTypeId');?>
        </td>
    </tr>
    <tr class="descriptions">
        <td class="backward description">
            Description<br />
            <? formTextarea('backwardDescription',20,1,null,'class="autoexpandHeight"'); ?></td>
        <td class="backward max">
            <small>max</small><br />
            x&nbsp;<?formInteger('backwardMax',1,1000000,null,null,'style="width:5em"')?>
        </td>
        <td class="arrow up"></td>
        <td class="arrow down"></td>
        <td class="forward max">
            <small>max</small><br />
            <?formInteger('forwardMax',1,1000000,null,null,'style="width:5em"')?>&nbsp;x
        </td>
        <td class="forward description">
            Description<br />
            <? formTextarea('forwardDescription',20,1,null,'class="autoexpandHeight"'); ?>
        </td>
    </tr>
    <tr class="to recordType">
        <td colspan="6" style="text-align: center">
            <? $recordTypeSelect->redisplay('toRecordTypeId'); formPlaceholder('toRecordTypeId'); ?>
        </td>
    </tr>
</table>

