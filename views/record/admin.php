<script>
var editMode=true;

$(function(){

    // Move the error summary underneath the record family tree details
    $('div.errorSummary').remove().insertAfter('div.recordFamily');

    $('#graphHelpButton').on('click',function(){
        modal({
            title: 'Graph Help',
            html: $('#graph-help'),
        });
        return false;
    });;

    $('#hideGraphButton').on('click',function(){
        $('#showGraphButton').css('display','inline-block');
        $('.graph-holder').hide();
        $(this).hide();
        $('#maximizeGraphButton').hide();
        $('#minimizeGraphButton').hide();
        $('#nodeInfoPanel').hide();
        return false;
    });

    $('#showGraphButton').on('click',function(){
        $(this).hide();
        $('#maximizeGraphButton').show();
        $('#hideGraphButton').show();
        $('.graph-holder').show();
        $('#graph').get(0).contentWindow.resizeHandler();
    });

    $('a.createLabel').on('click',function(){
        var newWindow = window.open('../label/print.php?recordId=<?wsp("id")?>','_blank');
        newWindow.addEventListener('load',function(){
            console.log('Create Label Window Loaded');
            newWindow.registerLabelId = function(labelId){
                // Put the new label ID in the Assign new label ID box
                // This isn't actually necessary since the label has been assigned to the record on creation
                // However... this gives the users some visual feedback that the association has happened.
                $('#labelID').val(labelId);
            }
        });
        return false;
    });
});
</script>
<?

include(LIB_DIR.'/shareLinkTools.php');
shareLinkJavascript();
?>
<a href="admin.php?mode=getShareLink&id=<?=$id?>" class="getShareLink">Share link</a>
<script>
    console.log($('ul.btn-list.top'));
    $(function(){$('a.getShareLink').detach().insertBefore('h1');});
</script>
<?

formHidden('parentId');
$parentId = (int)ws(ws('id')?'record_parentId':'parentId');
global $parentName;
?>
<a style="display: none;" id="showGraphButton" href="#">View Graph</a>
    <div class="recordDataHeader">
    <div class="graph-holder">
        <iframe id="graph" src="graph.php?id=<?=wsp('id')?>"></iframe>
        <button id="hideGraphButton">Hide Graph</button>
        <a id="maximizeGraphButton" href="graph.php?id=<?=wsp('id')?>" target="_blank">Maximise Graph</a>
        <button id="graphHelpButton">Graph Help</button>
    </div>
    <div id="graph-help" style="display:none">
        <?
            $cms = cms('Node graph help text',1);
            if (strlen($cms)>20) echo $cms;
            else { ?>
                <p><b>Click a node</b> to centre the graph on that node and pull up a preview of the record data.</p>
                <p><b>Double click a node</b> to switch from viewing parent-child (intrinsic) relationships, to cross-record (extrinsic) relationships.</p>
                <p><b>Hover over a link</b> to view all relationships between two nodes (applies to extrinsic view only).<p>
                <p><b>Click a link</b> to toggle through relationships between two nodes (applies to extrinsic view only).<p>
            <? }
        ?>
    </div>

    <div class="recordMetadata">
        <section class="recordFamily">
            <? if($parentId) { ?>
                <div class="ancestors form-row half">
                    <h2>Ancestors</h2>
                    <div class="parent btn-box">
                        <span class="label">Parent:</span>
                        <span class="value"><a href="admin.php?id=<?=$parentId?>"><?=$parentName?></a></span>
                        <div class="btn-box-btn">
                            <a class="btn" href="admin.php?id=<?=$parentId?>">View</a>
                        </div>
                    </div>
                    <? if (count($ancestors)>1) { ?>
                        <div class="allAncestors btn-box">
                            <span class="label">All ancestors:</span>
                            <span class="count">
                                <?=count($ancestors)?>
                            </span>
                            <div class="btn-box-btn">
                                <a class="btn" href="list.php?filter_record:id_in=<?=implode(',',array_keys($ancestors))?>">View</a>
                            </div>
                        </div>
                    <? } ?>
                </div>
            <? } ?>
            <? if(ws('id') && ws('record_lastSavedAt')) { ?>
                <div class="descendants form-row half">
                    <h2>Descendants</h2>
                    <? if (count($children)) { ?>
                        <div class="children btn-box">
                            <? if (count($children)==1) { ?>
                                <span class="label">Only child:</span>
                                <span class="value">
                                </span>
                                <div class="btn-box-btn">
                                    <a class="btn" href="admin.php?id=<?=array_keys($children)[0]?>">View</a>
                                </div>
                            <? } else { ?>
                                <span class="label">Children:</span>
                                <span class="count">
                                    <?=count($children)?>
                                </span>
                                <div class="btn-box-btn">
                                    <a class="btn" href="list.php?filter_record:path_sw=<?=wsp('record_path')?>&filter_record:depth_eq=<?=1+ws('record_depth')?>">View</a>
                                </div>
                            <? } ?>
                        </div>
                    <? } ?>
                    <? if (count($descendants)>count($children)) { ?>
                        <div class="allDescendants btn-box">
                            <span class="label">All descendants:</span>
                            <span class="count">
                                <?=count($descendants)?>
                            </span>
                            <div class="btn-box-btn">
                                <a class="btn" href="list.php?filter_record:path_sw=<?=wsp('record_path')?>_">View</a>
                            </div>
                        </div>
                    <? } ?>
                    <div>
                        <? if (canDo('create','recordTypeId:'.ws('record_typeId'))) { ?>
                            <a class="btn" href="admin.php?id=0&parentId=<?wsp('id')?>">+ New child record</a>
                        <? } ?>
                    </div>
                </div>
            <? } ?>
        </section>


        <?
        # =============================================================================================
        # RELATIONSHIP MANAGEMENT =====================================================================
        # =============================================================================================

        if(count($newRelationshipTypeSelect->options) > 1) { ?>
            <section class="relationships">
                <h2 id="relationshipsHeading">
                    Relationships
                    <span class="count relationshipCount"></span>
                </h2>

                <div class="questionAndAnswer relationship add showOnEdit">
                    <div class="question">
                        <? $newRelationshipTypeSelect->display(); ?>
                    </div>
                    <div class="answer">
                        <? formOptionbox('relationshipLinkId', array(), 'id="findPartnerRelationshipLinkId" style="display:none"') ?>
                        <div id="partnerRecordNoChoice"></div>
                        <div id="choosePartnerRecord" style="display:none">
                            <? $ttsId = formTypeToSearch(array(
                                'name' => 'toRecordId',
                                'hidden' => 'true',
                                'url' => 'admin.php?id='.ws('id'),
                                'size' => 40,
                                'id' => 'findPartnerRecordTts',
                                'showNoResults' => false,
                                'includeAddNew' => 'Add new record',
                                'searchParameters' => array( 'mode'=>'ttsSearch', 'id'=>ws('id'), 'relationshipLinkId'=>'' ),
                            )); ?><br>
                            <div class="info"><?=cms('Find partner record instructions',1,'Type part of the record name, or the label ID')?></div>
                        </div>
                        <div id="maxRelationshipsWarning" style="display:none" class="warning">
                            <?=cms('Max relationships reached',1,'This record already has the maximum number of relationships of this type')?>
                        </div>
                        <script>
                            // Relationship management

                            var numRelationships=0;
                            var hashParameters = parseHashParameters();

                            function loadRelationships() {
                                $('<div></div>').insertAfter('#relationshipsHeading').load('admin.php?mode=showRelationships&id=<?=(int)$id?>',{}, function () {
                                    if(!editMode) {
                                        $(this).find('button.delete').hide();
                                    } else {
                                        // BenJ 20240108
                                        // I don't know why we would want to remove the list of relationships in edit mode
                                        // Commenting this out until we remember why!
                                        //$('div.relationship.list, div.relationship.empty').remove();
                                    }
                                    $(this).children().unwrap();
                                });
                            }
                            $(loadRelationships);

                            // If the page has just been loaded and a relationship has just been defined then
                            // if there is a parent window get it to refresh its relationship list
                            <?
                            global $newRelationship;
                            if (isset($newRelationship) && $newRelationship) {
                                ?>
                                if (window.opener!==null && typeof(window.opener.loadRelationships)=='function') {
                                    window.opener.loadRelationships();
                                }
                                <?
                            }
                            ?>

                            if (window.opener!==null && typeof(window.opener.loadRelationships)=='function') {
                                // When they click "save and close" we don't want them to go back to the list page
                                // Instead we want this page to reload so that this code here can run and we can
                                // close the tab
                                if (hashParameters.hasOwnProperty('closeWindow')) window.close();

                                $(function(){
                                    $('#dataEntryForm').attr('action',$('#dataEntryForm').attr('action')+'#closeWindow');
                                    $('button.saveAndClose').val('save');
                                });
                            }

                            var findPartnerRelationshipLinkId = $('#findPartnerRelationshipLinkId');

                            $('section.relationships').on('click','div.relationship button.delete',function(e){
                                var self = $(e.target);
                                self.closest('div.questionAndAnswer').remove();
                                $.post('admin.php',{
                                    mode : 'deleteRelationship',
                                    id : self.data('relationshipid')
                                });
                                return false;
                            });

                            $('select.relationship').on('change',function(){
                                let self = $(this);
                                let relationship = self.val();
                                var findPartnerRecordTts = $('#findPartnerRecordTts');

                                $('#choosePartnerRecord').hide();
                                $('#findPartnerRelationshipLinkId').hide();
                                $('#partnerRecordNoChoice').hide();

                                findPartnerRecordTts.val('');
                                if (!relationship.length) return;

                                $.post('admin.php',{ id:'<?=$id?>', mode:'relationshipOptions',relationship:relationship,recordTypeId:<?=json_encode(ws('record_typeId'))?>}, function(relationshipOptions){

                                    const displayTtsBox = function() {
                                        findPartnerRecordTts.val('').closest('.answer').find('#maxRelationshipsWarning').hide();
                                        relationshipLinkId = findPartnerRelationshipLinkId.val();
                                        if (!relationshipLinkId) {
                                            findPartnerRecordTts.closest('#choosePartnerRecord').hide();
                                        } else {
                                            // Check we haven't reached the limit
                                            if (relationshipLinkId<0) {
                                                findPartnerRecordTts.val('').closest('.answer').find('#maxRelationshipsWarning').show();
                                                $('#partnerRecordNoChoice').hide();
                                            } else {
                                                findPartnerRecordTts.closest('#choosePartnerRecord').show();
                                                ttsSearchParameters[<?=$ttsId?>]['relationshipLinkId'] = relationshipLinkId;
                                                if (hashParameters.hasOwnProperty('relatedRecordName')) {
                                                    let ttsVal = hashParameters.relatedRecordName.length ? hashParameters.relatedRecordName : 'Record ID: '+hashParameters.relatedRecordId;
                                                    findPartnerRecordTts.val(ttsVal);
                                                    $('input[name=toRecordId]').val(hashParameters.relatedRecordId);
                                                }
                                            }
                                        }
                                    }

                                    findPartnerRelationshipLinkId.empty();
                                    for( let relationshipLinkId in relationshipOptions ) {
                                        let value;
                                        // if the max number of relationships has been exceeded then set the value to -1
                                        if (relationshipOptions[relationshipLinkId].existing>=relationshipOptions[relationshipLinkId].max) {
                                            value=-1;
                                        } else {
                                            value=parseInt(relationshipLinkId);
                                        }
                                        findPartnerRelationshipLinkId.append($(
                                            '<option\
                                                data-recordtypeid="'+relationshipOptions[relationshipLinkId].recordTypeId+'"\
                                                data-reciprocalrelationshipdescription="'+relationshipOptions[relationshipLinkId].reciprocalRelationshipDescription+'"\
                                                data-reciprocalrelationshiplinkid="'+relationshipOptions[relationshipLinkId].reciprocalRelationshipLinkId+'"\
                                                value="'+value+'">'+
                                                htmlspecialchars(relationshipOptions[relationshipLinkId].name)+
                                            '</option>'
                                        ));
                                    }

                                    // See if we have been passed in a relationship that needs to be created
                                    if (Object.keys(relationshipOptions).length>1) {
                                        findPartnerRelationshipLinkId.prepend($('<option value="" selected>-- Select record type --</option>'));
                                        findPartnerRelationshipLinkId.off('change').on('change',function(){
                                            displayTtsBox();
                                        });
                                        findPartnerRelationshipLinkId.show();
                                        if (relationshipOptions.hasOwnProperty(hashParameters.relationshipLinkId)) {
                                            findPartnerRelationshipLinkId.val(hashParameters.relationshipLinkId);
                                            findPartnerRelationshipLinkId.trigger('change');
                                        }

                                    } else {
                                        let relationshipLinkId = Object.keys(relationshipOptions)[0];
                                        $('#partnerRecordNoChoice').text('the '+relationshipOptions[relationshipLinkId].name+' identified as:').show();
                                        displayTtsBox();
                                    }

                                },'json')

                            });
                            $('#findPartnerRecordTts').on('addNew',function(){
                                let relationship=findPartnerRelationshipLinkId.find('option:selected').data();
                                let params = $.param({
                                    edit                     : '',
                                    relationshipDescription    : relationship.reciprocalrelationshipdescription,
                                    relationshipLinkId        : relationship.reciprocalrelationshiplinkid,
                                    relatedRecordName        : <?=json_encode($recordName)?>,
                                    relatedRecordId          : <?=(int)ws('id')?>,
                                });
                                let url = 'admin.php?mode=update&parentId=0&record_typeId='+relationship.recordtypeid+'&anchor='+encodeURIComponent(params);
                                window.open(url);
                            });

                            // See if we have been passed in a relationship that needs to be created
                            if (hashParameters.hasOwnProperty('relationshipDescription')) {
                                let selectRelationship = $('select.relationship');
                                selectRelationship.val(hashParameters.relationshipDescription);
                                // Check we actually found such a relationship
                                if (selectRelationship.val()) selectRelationship.trigger('change');
                            }
                        </script>
                    </div>
                </div>
            </section>
        <? }
        # =============================================================================================
        # end of relationship management ==============================================================
        # ============================================================================================

        # =============================================================================================
        # OWNERSHIP ===================================================================================
        # =============================================================================================
        ?>
        <section class="ownership">
            <header>
                <h2>Ownership</h2>
            </header>
            <div class="questionAndAnswer ownership form-row">
                <div class="question">
                    Person
                </div>
                <div class="answer">
                    <? global $ownerSelect; $ownerSelect->display(); ?>
                </div>
            </div>
            <div class="questionAndAnswer ownership form-row">
                <div class="question">
                    Project
                </div>
                <div class="answer">
                    <? global $projectSelect; $projectSelect->display(); ?>
                </div>
            </div>
        </section>

        <?
        # =============================================================================================
        # LABELS ===================================================================================
        # =============================================================================================
        ?>
        <section class="labels">
            <header>
                <h2>Labels</h2>
            </header>
            <div class="questionAndAnswer form-row">
                <label for="labelID">
                    <div><?
                        global $labelIds;
                        if (!count($labelIds)) echo cms('No labels assigned',0);
                        else echo implode(',',$labelIds);
                    ?></div>
                    <div class="showOnEdit">
                        Assign label: <? formInteger('labelId',0,1000000, null, null, 'id="labelID"'); ?>
                        <? inputError('labelId'); ?><br />
                        <input dependsOn="labelId gt 0" class="small" type="submit" value="Submit" /><br />
                        <a class="btn small" href="#" clickToShow="#removeLabel"><?=cms('Remove label')?></a>
                        <? if (ws('id')) { ?>
                            <a class="btn small createLabel" href="#" ><?=cms('Create new label')?></a>
                        <? } ?>
                    </div>
                    <div id="removeLabel" style="display:none">
                        Remove label :<? formInteger('removeLabelId',0,1000000); ?>
                        <? inputError('removeLabelId'); ?><br />
                        Enter the ID of the label you wish to disassociate from this record.<br />
                        <input class="small" type="submit" value="Submit" />
                        <? if (inputError('removeLabelId',false)) {?>
                            <script>
                                $(function(){$('[clickToShow=\\#removeLabel]').trigger('click');});
                            </script>
                        <? } ?>
                    </div>
                </label>
            </div>
        </section>
    </div>
</div>

<div class="content recordData">
    <div class="questionAndAnswerContainer form-grid">
        <?
        foreach($dataFields as $id => $dataField) {
            $dataField->displayRow(false);
        }
        ?>
    </div>
</div>

<script>
    let inheritanceStartupChecks;

    $(function () {
        function setInheritanceDisabled(inheritanceControl) {
            let controlType = inheritanceControl.prop('type');
            let inherited;
            if(controlType == 'checkbox') {
                inherited = inheritanceControl.prop('checked');
            } else {
                inherited = !!parseInt(inheritanceControl.val());
            }

            let input = inheritanceControl
                .closest('div.answer').find('input,textarea,select')
                .filter(function () { return !$(this).is(inheritanceControl); });

            if(controlType != 'hidden') {
                if(inherited) {
                    inheritanceControl.data('changedAnswer', input.val());
                    input.val(inheritanceControl.data('parentAnswer'));
                } else {
                    input.val(inheritanceControl.data('changedAnswer'));
                }
            }
            input.prop('disabled', inherited);
        }

        $('input[type=checkbox].inherited').on('change', function () {
            setInheritanceDisabled($(this));
        });

        $('input[type=checkbox].inherited').each(function () {
            let checkbox = $(this);
            let input = checkbox
                .closest('div.answer').find('input,textarea,select')
                .filter(function () { return !$(this).is(checkbox); });

            checkbox.data('parentAnswer', JSON.parse(checkbox.attr('parentAnswer')));
            checkbox.data('changedAnswer', input.val());
        });

        inheritanceStartupChecks = function () {
            $('input[type=checkbox].inherited:checked').trigger('change');
            $('input[type=hidden].inherited').each(function () {
                setInheritanceDisabled($(this));
            });
        }
        inheritanceStartupChecks();
    });

    // This function is called by the label creation page which is spawned as a new tab
    window.addLabelId = function(labelId) {
        console.log(labelId);
    }
</script>

<?
// This gets populated by javascript on form submission
formHidden('hiddenFields');
?>
<script>
    window.onCheckAbortUploads = function() {
        var leavePage = window.confirm('<?=cms('Some uploads are still in progress if you continue these will be aborted. Are you sure you want to continue',-1)?>');
        if(leavePage) {
            exitCheckFunctions = []; // We've already checked uploads so there's no point asking again on page unload
            return true;
        } else {
            return false;
        }
    }

    // Handle form submission
    $('#dataEntryForm').on('submit',function(){

        // We need to send the server a list of which input fields were hidden
        hiddenFields = $(':input,iframe').filter(':hidden[name]').not('[name^=adminCheckStatus]').not('[name^=dataFieldInherited]').map(function() {  if(matches = $(this).attr('name').match(/\[(\d+)\]/)) return matches[1];}  ).get();
        // Make sure the list of field ID's doesn't contain repetitions of the same ID
        hiddenFields = hiddenFields.filter(function (value, index, self) { return self.indexOf(value) === index; });
        hiddenFields = hiddenFields.join(',');

        $('input[name="hiddenFields"]').val(hiddenFields);

    });

    $(function(){
        // Put a set of buttons at the top of the page
        $('.btn-list.bottom').clone().removeClass('bottom').addClass('top').prependTo('#dataEntryForm');

        function notifyEdit() {
            <? if(ws('id')) { ?>
                const id = <?=(int)ws('id')?>;
                $.post('admin.php', {mode: 'logEditAccess', id: <?=(int)ws('id')?>});
            <? } ?>
            return false;
        }

        if (!<?=json_encode($editMode)?> && !parseHashParameters().hasOwnProperty('edit')) {
            // Start off with all the inputs disabled
            editMode = false;
            var inputs = $('.questionAndAnswer .answer').find('textarea,select,input');
            var saveButtons = $('.btn-list button[type=submit]');
            var hideOnEdit = $('.hideOnEdit');
            var showOnEdit = $('.showOnEdit,.saveDefault');
            <? if (canDo('edit',ws('id'),'recordTypeId:'.ws('record_typeId'))) { ?>
                var editButton = $('<li><button class="btn">Edit</button></li>').appendTo($('.btn-list'));
                editButton.on('click',function(){
                    showOnEdit.show();
                    hideOnEdit.hide();
                    // Relationships are loaded asynchronously so we can just use "hideOnEdit" for these
                    $('div.questionAndAnswer.relationship.empty').hide();
                    // Relationships are loaded dynamically so we have to show the delete button for whichever
                    // are visible at the time.
                    $('div.questionAndAnswer.relationship.list button.delete').show();

                    inputs.prop('disabled',false);
                    saveButtons.show();
                    editButton.hide();
                    inheritanceStartupChecks();
                    notifyEdit();
                    return false;
                });
            <? } ?>
            var addRelationship = $('div.questionAndAnswer.relationship.add').hide();

            hideOnEdit.show();
            showOnEdit.hide();
            inputs.prop('disabled',true);
            saveButtons.hide();
        } else {
            notifyEdit();
        }

    });
</script>
