<?

include(VIEWS_DIR.'/header.php');

?>
<style>
    iframe {
        box-sizing: content-box;
        display: block;
        margin: 0 auto;
        width: 211mm;
        height: 298mm;
        border: 2px solid black;
    }
    .layout {
        width: 100%;
    }
</style>

<script>
    $(function(){
        $('button.close').on('click',function(){
            window.close();
        })

        $('button.print').on('click',function(){
            window.frames["pagePreview"].focus();
            window.frames["pagePreview"].print();
        })
        $('button.downloadPNG').on('click',function(){
            // This function is defined in the iframe
            downloadPNG();
        })
        $('button.downloadPDF').on('click',function(){
            // This function is defined in the iframe
            downloadPDF();
        })

    });
</script>
<h1><?=cms('Create Label',0)?></h1>
<hr class="contentStart">

<div class="content mainContent">

    <form >
        Layout:
        <? formOptionbox('layout',['A4 3x9'=>'3x9','A4 5x13'=>'5x13'],'onChange="changeLayout(this.value)" class="dontExpand"'); ?>
        
    </form>
    <br />
    <button class="btn close">Close</button>
    <button class="btn print">Print</button>
    <button class="btn downloadPDF">Download PDF</button>
    <? if (ws('labelId')) { ?>
        <button class="btn downloadPNG">Download PNG</button>
    <? } ?>
    <div style="text-align:center;">
        <h2 style="text-align:center; display: block; width:100%">Preview</h2>
        <? if (ws('labelId')) { ?>
            <p>Click the empty slots below to move the label to a different position on the page</p>
        <? } else { ?>
            <p>A preview using dummy labels is shown below. Please note that generating a full page of labels for download will take a minute or two</p>
        <? } ?>
        <iframe name="pagePreview" src="print.php?labelId=<?wsp('labelId')?>&securityCode=<?wsp('securityCode')?>"></iframe>
    </div>

</div>

<? if (ws('labelId')) { ?>
<script>
    window.setInterval(function(){
        if (typeof window.registerLabelId == 'function') window.registerLabelId(<?=(int)ws('labelId')?>);
    })
</script>
<? }

include(VIEWS_DIR.'/footer.php');
