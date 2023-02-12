<?

function formLabel($for,$label,$contentCallback) {
    $args = func_get_args();
    array_splice($args,2,1);
    ob_start();
    $result = call_user_func_array($contentCallback,$args);
    $ui=ob_get_contents();
    ob_end_clean();
    if ($result === false) {
        echo $ui;
    } else {
        echo '<div class="question">';
        echo '<label for="'.htmlspecialchars($args[0]).'">'.cms($args[1],0).'</label>';
        echo $ui;
        echo '</div>';
    }
}


function formColour( $name, $default=null, $extra='' ) {
    $type = 'color';
    formBox( 'color', $name, 0, '', $default, $extra );
}

// set the size to a negative number to indicate a PASSWORD box
function formTextbox( $name, $size=10, $maxlength='', $default=null, $extra='' ) {
    $type = 'text';
    if ($size < 0) {
        $type = 'password';
        $size = $size * -1;
    }
    formBox( $type, $name, $size, $maxlength, $default, $extra );
}

function formInteger( $name, $min=null, $max=null, $step=null, $default=null, $extra='' ) {
    if (is_null($step)) $step=1;
    else $step=round($step);

    $extra = preg_replace('/(class\s*=\s*["\'])/','\\1integer ',$extra, 1, $replaced);
    if (!$replaced) $extra .= ' class="integer"';

    formFloat( $name, $min, $max, $step, $default, $extra );
}

// You can set the size of the input (i.e. number of decimal places displayed) by setting the step size e.g. 0.001 for 3 d.p.
function formFloat( $name, $min=null, $max=null, $step=null, $default=null, $extra='' ) {
    foreach( array('min','max','step') as $thing ) {
        if (!is_null($$thing) && !strlen($$thing)) $$thing=null;
        if (!is_null($$thing)) $extra.=' '.$thing.'='.htmlspecialchars($$thing);
    }
    $size = is_null($max)?'':ceil(log10($max));

    formBox( 'number', $name, $size, $size, $default, $extra );
}

function formEmail( $name, $size=10, $maxlength='', $default=null, $extra='' ) {
    formBox( 'email', $name, $size, $maxlength, $default, $extra );
}

function formDate( $name, $default=0, $min=0, $max=0, $extra='' ) {
    global $WS;
    static $jsDone = false;

    if (isset( $WS[$name] ) && $WS[$name]>0 ) $value = (int)$WS[$name];
    else $value = (int)$default;

    if ($min<1) $min = '1970-1-1';
    else $min = date('Y-m-d',$min);

    if ($max<1) $max = '2038-1-1';
    else $max = date('Y-m-d',$max);

    $extra .=' unixtime="'.$value.'" class="coreDateField" min="'.$min.'" max="'.$max.'"';

    formBox( 'date', $name, '', '', [$default], $extra );
    if (!$jsDone) {
        ?><script>
        $(function(){
            function dateChangeHandler(){
                var self = $(this);
                let value = self.val();
                if (value.length) value = new Date(value).getTime()/1000;
                else value='';
                $(this).data('hiddenField').val(value);
            }
            $('input.coreDateField').each(function(){
                let self = $(this);
                unixtime = parseInt(self.attr('unixtime'));
                if (unixtime>0) {
                    this.value = new Date(unixtime * 1000).toISOString().split('T')[0];
                }
                // Don't use an <input type="hidden"> because some forms need to determine if fields
                self.data( 'hiddenField',
                    $('<input type="text" style="position:absolute; left: -1000px; width: 10px;"/>').attr('name',this.name).insertAfter(this)
                );
                this.name='';
            }).on('change',dateChangeHandler);
            // We need to trigger the change handler on page load to initialise the date display
            // BUT... we can't just call .trigger('change') because sometimes there are other onchange handlers
            // that would then get triggered
            $('input.coreDateField').each(function(){
                // while we're here lets move the event handler we added to the front of the queue
                // we're always going to want to run this first before we try and do anything with the newly computed time value;
                var changeHandlers = $._data(this, "events").change;
                changeHandlers.unshift(changeHandlers.pop());
                // Then call the change handler to initialise this input (without actually triggering a change event)
                dateChangeHandler.call(this);
            });
        });
        </script><?
        $jsDone = true;
    }
}

function formBox( $type, $name, $size=10, $maxlength='', $default=null, $extra='' ) {
    global $WS;
    if (is_array($default)) $default = $default[0];
    else if (isset( $WS[$name] )) $default = $WS[$name];

    echo '<input type="'.$type.'" name="'.htmlspecialchars($name).'" ';
    if ($size>0) echo 'SIZE="'.$size.'" ';
    if ($maxlength>0) echo 'maxlength="'.$maxlength.'" ';
    echo 'value="'.htmlspecialchars($default).'" '.$extra.' />';
}

function formTextarea( $name, $cols = 0, $rows = 0, $default = null, $extra = '' ) {
    global $WS;
    $cols = $cols ? "cols=\"$cols\"" : '';
    $rows = $rows ? "rows=\"$rows\"" : '';
    echo "<textarea $rows $cols name=\"".htmlspecialchars($name).'" '.$extra.' >';
    if ($default == null) $default = isset($WS[$name])?$WS[$name]:'';
    echo htmlspecialchars($default);
    echo '</textarea>';
}

/*
 * Two-list selector
 * $availableList & $selectedList should either be a formOptionbox or sql to create one,
 * OR pass a single query as $availableList returning 'option', 'value', 'selected' to populate both boxes
 *    NB `option` may have to be backtick-quoted as it's a reserved word in some mysql versions
 * $labels can contain 'availableTitle', 'selectedTitle' and 'explanation', empty string to be blank, unset to use default
 */
class formPicklist {

    private static $doJS=true;
    private $labels;
    private $availableList;
    private $selectedList;
    private $disabled;

    function __construct($name, $availableList, $selectedList = null, $labels = array()) {
        global $DB;

        if(!isset($labels['availableTitle'])) $labels['availableTitle'] = cms('Available');
        if(!isset($labels['selectedTitle'])) $labels['selectedTitle'] = cms('Selected');
        if(!isset($labels['explanation'])) $labels['explanation'] = cms('Click an item to move it to the other list').'<br />'.cms('Click and drag to move multiple items').'<br />'.cms('Click in the box and press Ctrl-a to move all items');

        if($selectedList !== null) {
            if(!is_object($availableList)) {
                $availableList = new formOptionbox('_', $availableList);
            }

            if(!is_object($selectedList)) {
                $selectedList = new formOptionbox('_', $selectedList);
            }
        }
        else {
            $availableOptions = $selectedOptions = array();
            $allOptions = $DB->getRows($availableList);
            foreach($allOptions as $option) {
                if(isset($option['selected']) && $option['selected']) $selectedOptions[$option['option']] = $option['value'];
                else $availableOptions[$option['option']] = $option['value'];
            }
            $availableList = new formOptionbox('_', $availableOptions);
            $selectedList = new formOptionbox('_', $selectedOptions);
        }
        $availableList->setName($name.'_available');
        $availableList->setDefault(null);
        $availableList->setExtra('class="picklistList picklistAvailable" id="picklist_'.$name.'_available"');
        $availableList->setMultiple(true);
        $selectedList->setName($name);
        $selectedList->setDefault(null);
        $selectedList->setExtra('class="picklistList picklistSelected" id="picklist_'.$name.'_selected"');
        $selectedList->setMultiple(true);

        $this->selectedList = $selectedList;
        $this->availableList = $availableList;
        $this->labels = $labels;
        $this->options = array_merge($selectedList->options,$availableList->options);
    }

    function disable($value=true) {
        $this->disabled=$value;
    }

    function display() {
        if ($this->disabled) {
            $this->selectedList->disable();
            $this->selectedList->display;
            return;
        }
        $selectedList = $this->selectedList;
        $availableList = $this->availableList;
        $labels = $this->labels;
        ?>
        <div class="picklistHolder">
            <div class="picklistListHolder">
                <?
                if($labels['availableTitle']) {
                    echo '<div class="picklistListTitle">'.$labels['availableTitle'].'</div>';
                }
                $availableList->display();
                ?>
            </div>
            <div class="picklistListHolder">
                <?
                if($labels['selectedTitle']) {
                    echo '<div class="picklistListTitle">'.$labels['selectedTitle'].'</div>';
                }
                // If nothing selected from the picklist then nothing will be submitted
                // It is impossible to know on the server side whether the select box just wasn't there or was submitted with nothing in it
                // so we add a hidden field with a special key
                // This is picked up by the core validation.php and replaced with an empty array
                // If there are some real values then the core validation.php will strip this out
                // The CORE_INPUT_EMPTY constant is defined in validation.php
                formHidden($selectedList->getName().'['.CORE_INPUT_EMPTY.']','1');
                $selectedList->display();
                ?>
            </div>
            <?
            if($labels['explanation']) {
                echo '<div class="picklistExplanation">'.$labels['explanation'].'</div>';
            }

            if(self::$doJS) { ?>
                <script>
                    $(function () {
                        $('.picklistList').on('mousedown','option', function (ev) {
                            // The next 3 lines prevent the option actually being selected
                            // This is required so that any onChange handlers work properly
                            // Without this any onChange handlers added later in the code get called before this one
                            // This means they see the options before the code in this function runs (i.e. before the option has been added/removed).
                            ev.preventDefault();
                            this.blur();
                            window.focus();
                            console.log(ev.target);
                            let moveOption = $(this);
                            let moveFromList = moveOption.parent();
                            let moveToList = moveFromList.closest('.picklistHolder').find('select').not(moveFromList);

                            moveOption.remove().appendTo(moveToList)
                            moveToList.trigger('change');
                            moveFromList.trigger('change');
                        });

                        $('.picklistHolder').closest('form').on('submit', function () {
                            $(this).find('.picklistSelected option').prop('selected', true);
                        });

                        // TODO: should we have some other way of signalling whether to do this?
                        // it disagrees with the new redesign but should probably still be there in core
                        //$('div.picklistHolder').each(function () {
                        //    var thisHolder = $(this);
                        //    var maxWidth = 0;
                        //    thisHolder.find('select').each(function() {
                        //        var thisWidth = $(this).width();
                        //        if(thisWidth > maxWidth) maxWidth = thisWidth;
                        //    });

                        //    thisHolder.find('select').width(maxWidth);
                        //});

                    });
                </script>
                <?
                self::$doJS = false;
            } ?>
        </div>
        <?
    }
}

function formPicklist($name, $availableList, $selectedList = null, $labels = array()) {
    $picklist = new formPicklist($name, $availableList, $selectedList = null, $labels = array());
    $picklist->display();
}

function formMultiHidden($regexp, $hash='') {
    global $WS;
    if (!is_array($hash)) $hash = $WS;
    $fields = fuzzyLookup( $hash, $regexp );
    foreach ( $fields as $key=>$value) {
        formHidden( $key, $value );
    }
}

function formPlaceholder( $notUsed ) {
    // this function is intetionally empty
}

/*
This function creates a HIDDEN input field
    If called with just one parameter it uses this as the NAME and looks for the value of the field with the same name in the workspace if this field is not set then the value is defaulted to ''
    If called with two parameters then these are taken to be the NAME and VALUE and no reference is made to the Workspace
    If called with three parameters then the first is taken to be the NAME, the second a workspace field to try and obtain the VALUE from (if this is empty then the value from the first parameter is used) and the third a default VALUE to us if the specified workspace field is not set
*/
function formHidden( $name, $arg1=null, $arg2=null, $arg3=null) {
    global $WS;
    $extra = '';
    $signed = false;
    $signingEntity = '';
    $sigLength = 0;
    if (is_array($name)) {
        $options = $name;
        $name = $options['name'];
        $extra = isset($options['extra']) ? $options['extra']:'';
        $value = isset($options['value']) ?    $options['value'] : ( isset($options['default']) ? $options['default'] : (     isset($WS[$name]) ?    $WS[$name] : ''    ) );
        $signed = (isset($options['signed']) && $options['signed']);
        $signingEntity = isset($options['signingEntity']) ? $options['signingEntity']:'';
        $sigLength = isset($options['sigLength']) ? $options['sigLength']:0;

        // If either signingEntity or sigLength is set then you don't have to set "signed" to true - it is assumed to be true in this case
        if (strlen($signingEntity)||$sigLength) $signed=true;

    }
    if ($arg3!==null) {
        // 4 argument call
        // name, workspace_field, default_value, $extra
        $extra = $arg3;
    }
    if ($arg2!==null) {
        // 3 argument form call
        // name, workspace_field, default_value
        if ($arg1 == '') $arg1 = $name;
        if (isset($WS[$arg1])) {
            $value = $WS[$arg1];
        } else {
            $value = $arg2;
        }
    } else if ($arg1!==null) {
        // 2 argument form call
        // name, value
        $value = $arg1;
    } else {
        // 1 argument form
        // name
        if (isset($WS[$name])) {
            $value = $WS[$name];
        } else {
            $value = '';
        }
    }
    if (is_array($value)) {
        $idx=0;
        $useKey=false;
        foreach ( $value as $key=>$content ) {
            if (((int)$key)!=$idx) $useKey=true;
            if (!$useKey) $key='';
            $arrayName = $name.'['.$key.']';
            if (is_array($content)) formHidden($arrayName,$content);
            else echo '<INPUT '.$extra.' TYPE="HIDDEN" NAME="'.htmlspecialchars($arrayName).'" VALUE="'.htmlspecialchars($content).'" />';
            $idx++;
        }
    } else {
        if($signed) $value = signInput($value,$signingEntity,$sigLength);
        echo '<INPUT '.$extra.' TYPE="HIDDEN" NAME="'.htmlspecialchars($name).'" VALUE="'.htmlspecialchars($value).'" />';
    }
}

function formCheckbox( $name, $value=null, $default=null, $extra=null, $label=null ) {
    global $WS;
    if ($value==null) $value = 1;

    $id = '';
    if ($label!==null) {
        echo '<span class="checkbox">';
        static $idCount;
        if (!isset($idCount)) $idCount=1;
        $id = htmlspecialchars($name).'_'.$idCount++;
    }
    echo '<input ';
    if ($id) echo 'id="'.$id.'" ';
    if ($extra) echo $extra;
    echo ' name="'.htmlspecialchars($name).'" type="checkbox" value="'.htmlspecialchars($value).'"';
    if (is_null($default)) {
        if (isset($WS[$name]) && $WS[$name]==$value) echo 'checked="yes"';
    } else {
        if ($default==$value) echo 'checked="yes"';
    }
    echo ' />';
    if ($label!==null) {
        echo '<label for="'.$id.'"><span></span>'.htmlspecialchars($label).'</label><br/></span>';
    }
}

/*
$selector:
    #whateverId - all the checks in container #whateverId
    .whateverClass - all the in containers with class whateverClass or checks having class whateverClass
    whateverName - all checks having name whateverName
*/
function selectAllNoneChecks($selector, $labels = array(), $visibleOnly = false) {
    static $doJS = true;

    if(!isset($labels['prefix'])) $labels['prefix'] = cms('Select', 0);
    if(!isset($labels['allLink'])) $labels['allLink'] = cms('All', 0);
    if(!isset($labels['noneLink'])) $labels['noneLink'] = cms('None', 0);
    if(!isset($labels['resetLink'])) $labels['resetLink'] = cms('Reset', 0);

    ?>
    <div class="allNoneChecks" data-check-selector="<?=htmlspecialchars($selector)?>" data-visible-only="<?=(int)(bool)$visibleOnly?>">
        <?= $labels['prefix'] ?: '' ?>
        <a href="#" class="allNoneChecksLink" data-checks-checked="1"><?=$labels['allLink']?></a>
        <? if($labels['noneLink']) { ?>
            | <a href="#" class="allNoneChecksLink" data-checks-checked="0"><?=$labels['noneLink']?></a>
        <? }
        if($labels['resetLink']) { ?>
            | <a href="#" class="allNoneChecksLink" data-checks-checked="-"><?=$labels['resetLink']?></a>
        <? } ?>
    </div>
    <?

    if($doJS) { ?>
        <script>
            $(function () {
                $('.allNoneChecksLink').on('click', function (ev) {
                    var link = $(ev.target),
                        parentDiv = link.closest('div.allNoneChecks'),
                        newChecked = parseInt(link.attr('data-checks-checked')),
                        selector = parentDiv.attr('data-check-selector'),
                        visibilityCheck = parentDiv.attr('data-visible-only') ? ':visible' : '';

                    if(!(selector.substr(0, 1) === '.' || selector.substr(0, 1) === '#')) {
                        selector = 'input:checkbox[name="' + selector + '"]';
                    }
                    selector += visibilityCheck;

                    $(selector).add($(selector).find('input:checkbox'+visibilityCheck)).each(function() {
                        var elem = $(this);
                        elem.prop('checked', !isNaN(newChecked) ? newChecked : Boolean(elem.attr('checked')));
                    });

                    return false;
                });
            });
        </script>
        <?
        $doJS = false;
    }
}

function formRadio( $name, $value=null, $default=null, $extra='' ) {
    global $WS;
    if (is_null($value)) $value = 1;
    if (is_null($default)) $default = isset($WS[$name])?$WS[$name]:'';
    echo '<input '.$extra.' name="'.htmlspecialchars($name).'" type="radio" value="'.htmlspecialchars($value).'"';
    if ($default==$value) echo 'checked="yes"';
    echo ' />';
}

function formOptionbox( $name, $options, $extra='' ) {
    $optionbox = new formOptionbox($name, $options);
    $optionbox->display($extra);
}

function formYesNo($fieldName, $filter=true, $reverse=false, $invert=false, $extra='') {
    $options = $filter?array('-- all --'=>''):array();
    $yes = $invert ? '0' : '1';
    $no = $invert ? '1' : '0';
    if ($reverse) {
        $options['No']=$no;
        $options['Yes']=$yes;
    } else {
        $options['Yes']=$yes;
        $options['No']=$no;
    }
    $optionBox = new formOptionbox($fieldName,$options);
    $optionBox->display($extra);
}

function formTypeToSearchSupport() {
    static $alreadyOutput = false;
    if ($alreadyOutput) return;
    $alreadyOutput = true;
    ?>
        <style>
            .tts-click-cover {
                z-index: 1;
            }

            .tts-holder {
                position: relative;
            }

            .tts-holder .tts-search {
                z-index: 2;
            }

            .tts-results-holder {
                z-index: 3;
                display: none;
                position: absolute;
            }

            .tts-results-holder .tts-results-wrapper {
                overflow-y: scroll;
            }

            .tts-results-holder .tts-results-list {
                margin: 2px 0;
            }

            .tts-results-holder .tts-results-list li {
                cursor: pointer;
                white-space: nowrap;
            }
        </style>
        <script>
            ttsSearchParameters = {};

            $(function () {
                const MIN_SEARCH_CHARS = 3;
                let ttsCurrentSearch = null;

                function showTTSSearchResults(ttsId, results) {
                    let ttsHolder = $('#tts-' + ttsId);
                    let ttsResultsList = $('#tts-results-' + ttsId + ' ul');
                    let newLIs = [];
                    let resultsIsArray = Array.isArray(results);

                    for(let resIdx in results) {
                        let details;
                        // The results can either be and array of objects each with "value" and "item" keys
                        // ...or a hash with keys used for the return value and the value used for what to display
                        // ...or just a flat array in which case the same value is displayed and returned
                        if (typeof(results[resIdx])=='object') details=results[resIdx];
                        else if (resultsIsArray) details = { value: results[resIdx], item: results[resIdx] };
                        else details = { value: resIdx, item: results[resIdx] };
                        let newLI = $('<li tts-value="' + details.value + '">' + details.item + '</li>');
                        newLI.text(details.item);
                        newLIs.push(newLI);
                    }

                    let ttsInput = ttsHolder.find('input.tts-search');
                    if(!newLIs.length && ttsInput.attr('tts-show-no-results') === '1') {
                        newLIs.push($('<li class="noResults" tts-value=""><i>No results</i></li>'));
                    }

                    if (ttsInput.attr('tts-include-add-new')) {
                        let text = htmlspecialchars(ttsInput.attr('tts-include-add-new'));
                        newLIs.push($('<li class="addNew" tts-value=""><i>'+text+'</i></li>'));
                    }

                    ttsResultsList.empty();
                    if(newLIs.length) {
                        ttsResultsList.append(newLIs);
                        showTTSSearchResultsList(ttsId);
                    }
                }

                function showTTSSearchResultsList(ttsId) {
                    let ttsInput = $('#tts-' + ttsId + ' input.tts-search');

                    // The result list was getting cropped off by "overflow:hidden" on some ancestor
                    // Since we can't always control the CSS properties of all the ancestors the most reliable approach here
                    // is to detach the result list and append it to the body they work out the absolute position relative to the body
                    let offset = ttsInput.offset();
                    offset.top += ttsInput.innerHeight()-2;
                    let resultList = $('#tts-results-' + ttsId);
                    if (resultList.parent().prop('tagName')!=='body') {
                        resultList.detach().appendTo('body').data('ttsInput',ttsInput);
                    }
                    resultList
                        .show()
                        .outerWidth(ttsInput.innerWidth())
                        .offset(offset);

                }

                function hideTTSSearchResultsList() {
                    $('.tts-results-holder').hide();
                }

                $('.tts-search').on('focusout', function (e) {
                    hideTTSSearchResultsList();
                });

                $('.tts-search').on('focus', function () {
                    let ttsSearch = $(this);
                    let ttsId = ttsSearch.attr('tts-id');
                    let ttsResultCount = $('#tts-results-' + ttsId).find('li').length;

                    if(ttsSearch.val().length >= MIN_SEARCH_CHARS && ttsResultCount) {
                        showTTSSearchResultsList(ttsId);
                    }
                });

                $('.tts-search').on('keyup', function (ev) {
                    let ttsSearch = $(this);
                    let searchVal = ttsSearch.val();
                    let ttsId = ttsSearch.attr('tts-id');

                    if(ev.which === 27) { // Esc
                        ttsSearch.trigger('focusout');
                        return;
                    }

                    if(searchVal.length >= MIN_SEARCH_CHARS) {
                        if(ttsCurrentSearch !== null) {
                            ttsCurrentSearch.abort('Search cancelled');
                        }

                        searchParameters = { ttsSearch : searchVal };
                        if (typeof(ttsSearchParameters[ttsId])) searchParameters = { ...searchParameters, ...ttsSearchParameters[ttsId] };

                        ttsCurrentSearch = $.ajax({
                            method      : 'POST',
                            url         : ttsSearch.attr('tts-url'),
                            data        : searchParameters,
                            dataType    : 'json',
                            success     : function (data) {
                                showTTSSearchResults(ttsId, data);
                            },
                            error       : function (_jqXHR, _textStatus, _error) {
                                console.log(_jqXHR);
                                console.log(_textStatus);
                                console.log(_error);
                            }
                        });
                    } else {
                        hideTTSSearchResultsList();
                    }
                });

                $('ul.tts-results-list').on('mousedown', 'li', function () {
                    let selectedItem = $(this);
                    let ttsValue = selectedItem.attr('tts-value');
                    let ttsInput = selectedItem.closest('.tts-results-holder').data('ttsInput');

                    if (selectedItem.hasClass('addNew')) {
                        ttsSearch.trigger('addNew');
                    }

                    if(ttsValue === '') return;

                    // Save the current search value
                    ttsInput.val(selectedItem.text());

                    // Write the value to the hidden field if one is specified
                    ttsInput.closest('.tts-holder').find('.tts-value').val(ttsValue);

                    hideTTSSearchResultsList();
                });
            });
        </script>
    <?
}

function formTypeToSearch( $options ) {
    static $nextId = 0;

    // TODO avoid clashes with DataField_typeToSearch
    // either prefix $nextId differently here and there or hash it off microseconds (or use a uuid?)
    // check some agreed global to determine whether to output styles and JS
    $defaults = array(
        'name'              => '',
        'url'               => '',
        'extra'             => '',
        'id'                => '',
        'size'              => 20,
        'default'           => '',
        'showNoResults'     => false,
        // If includeAddNew is non-empty then a new option is added to the end of the list using the value provided in includeAddNew
        // If this option is selected then an "addNew" event is triggered on the tts input box
        // It is up to you to add an event handler for this addNew event
        'includeAddNew'     => '',
        // Send the selected result in a hidden input and call the textbox something else
        // If you want the value returned to differ from the value displayed (like a normal <select>) then you MUST use Hidden
        'hidden'            => false,
        'searchParameters'  => '',
    );

    foreach( array_keys($defaults) as $thing) {
        if(isset($options[$thing])) $$thing = $options[$thing];
        else $$thing = $defaults[$thing];
    }

    $textboxName = $hidden ? '' : ' name="'.htmlspecialchars($name).'"';
    $idAttribute = !strlen($id) ? '' : ' id="'.htmlspecialchars($id).'"';
    ?>
    <div class="tts-holder" id="tts-<?= $nextId ?>">
        <input
            type="text"
            class="tts-search"
            value="<?= htmlspecialchars($default) ?>"
            size="<?= $size ?>"
            tts-id="<?=$nextId ?>"
            tts-url="<?= htmlspecialchars($url) ?>"
            tts-include-add-new="<?= htmlspecialchars($includeAddNew) ?>"
            tts-show-no-results="<?= (int)$showNoResults ?>"
            <?= $textboxName.$idAttribute.' '.$extra ?>
        >
        <div class="tts-results-holder" id="tts-results-<?=$nextId ?>">
            <div class="tts-results-wrapper">
                <ul class="tts-results-list"></ul>
            </div>
        </div>
        <? if ($hidden) formHidden(array('name' => $name, 'value' => $default, 'extra' => 'class="tts-value"')); ?>
    </div>
    <?

    if($nextId == 0) formTypeToSearchSupport();

    if (is_array($searchParameters) && count($searchParameters)) {
        echo '<script>';
        echo 'ttsSearchParameters['.$nextId.']='.json_encode($searchParameters).';';
        echo '</script>';
    }

    $nextId++;

    return $nextId-1;
}

// ============================================================================
// ============================== OPTIONBOX ===================================
// ============================================================================

/* Some examples of usage

$ob = new formOptionbox( 'cgi_name' );
$ob = new formOptionbox( 'cgi_name', 'SELECT sql STATEMENT', '', default ); // this will use default db ($DB)
$ob = new formOptionbox( 'cgi_name', 'SELECT sql STATEMENT', $db, default );
$ob = new formOptionbox( 'cgi_name', array('option1' => value1, 'option2' => value2), [default] );
$ob->addOption('name','value');
$ob->addOption( array('option1' => value1, 'option2' => value2) );
$ob->addOption( array('option1', 'option2'), 1 ); // 1 for second argument indicates "flat" array i.e. not hash - so value==key for each option
$ob->addLookup('SELECT sql STATEMENT', [$db]); // if no database set here or when object created then this will use default db ($DB)
$ob->removeOption('value');
$ob->removeOption( array('value1', 'value2', 'value3') );
$ob->removeLookup('SELECT values_to_remove FROM lookup', [$db]);
$ob->defaultLookup('SELECT values_to_default FROM lookup', [$db]);
$ob->setDefault( 'default value' );
$ob->setDefault( array('selected','also selected','another selected value') );
$ob->addDefault( 'another default value' );
$ob->addDefaults( array('selected','also selected','another selected value') );
$ob->addDefaults( regexp, 'value'|'option' ); // ads all options for which the option/value (as specified) matches the regexp
$ob->setExtra( 'onChange="extra stuff to go in SELECT tag"' );
$ob->setName( 'new_name' );
$ob->setRows( 'rows' );
$ob->setMultiple( 'multiple'|'single'|1|0 );
$ob->setDb( $database_handle );
$ob->display();
$ob->showDefault()

*/

    class formOptionbox {

        var $dbh = '';
        var $autoDefault = 1;
        var $default = array();
        var $options = array();
        var $name;
        var $rows = '';
        var $extra = '';
        var $multiple = 0;
        var $rawOutput = 0;
        var $autoHide = FALSE;
        var $disabled = 0;
        var $isHidden = FALSE;

        /*Constructor
        /   $ob = new optionbox( 'chooser', ['multiple'|'single'|1|0, display_rows] );
        /   $ob = new optionbox( 'chooser', 'SELECT sql STATEMENT', '', default, ['multiple'|'single'|1|0, display_rows] );
        /   $ob = new optionbox( 'chooser', 'SELECT sql STATEMENT', $db, default, ['multiple'|'single'|1|0, display_rows] );
        /   $ob = new optionbox( 'chooser', array('option1' => value1, 'option2' => value2), default, ['multiple'|'single'|1|0, display_rows] );
        /   $ob = new optionbox( { options } );
        /        db                : Database handle
        /        name            : Form field name
        /        prefixOptions    : Hash of list items to add before the items generated by the query
        /        query            : SQL statement to pull back list of options
        /        query2            : "
        /        query3            : "
        /        suffixOptions    : Hash of list items to add after the items generated by the query
        /        default            : Default value/values (this is fed to $this->setDefault)
        /        multiple        : Value to be fed to $this->setMultiple
        /        displayRows        : number of rows to display (defaults to 1)
        */

        function formOptionbox( $name, $arg1 = '', $arg2 = null, $arg3 = null, $arg4 = '', $arg5 = '' ) {
            global $DB;
            if (is_array($name)) {
                $this->dbh = isset($name['db'])?$name['db']:$DB;
                $this->name = isset($name['name'])?$name['name']:'';
                if (isset($name['prefixOptions'])) $this->addOption( $name['prefixOptions'] );
                if (isset($name['query'])) $this->addLookup( $name['query'] );
                if (isset($name['query2'])) $this->addLookup( $name['query2'] );
                if (isset($name['query3'])) $this->addLookup( $name['query3'] );
                if (isset($name['multiple'])) $this->setMultiple( $name['multiple'] );
                if (isset($name['displayRows'])) $this->setRows( $name['displayRows'] );
                if (isset($name['default'])) $this->setDefault( $name['default'] );
                if (isset($name['suffixOptions'])) $this->addOption( $name['suffixOptions'] );

            } else {
                $this->dbh = $DB;
                $this->name = $name;
                if ( is_array($arg1) ) {
                    $this->addOption( $arg1 );
                    if ($arg2 <> null) $this->setDefault( $arg2 );
                } else {
                    if ( $arg1 <> '' && $arg1 <> 'multiple' && $arg1 <> 'single' && $arg1 <> 'MULTIPLE' && $arg1 <> 'SINGLE' && $arg1 <> '1' && $arg1 <> '0' ) {
                        if ( $arg2 == null ) { $this->dbh = $DB; }
                        else { $this->dbh = $arg2; }
                        $this->addLookup( $arg1 );
                        if ($arg3 <> null) $this->setDefault( $arg3 );
                        if ($arg4 <> '') { $this->setMultiple($arg4); }
                        if ($arg5 <> '') { $this->setRows($arg5); }
                    } else {
                        if ($arg1 <> '') $this->setMultiple($arg1);
                        if ($arg2 <> null) $this->setRows($arg2);
                    }
                }
            }
        }

        function addLookup( ) {
            global $DB;
            if ($this->dbh <> '') { $db = $this->dbh; }
            else { $db = $DB; }

            $data = $db->getHash( func_get_args() );

            foreach( $data as $key=>$value ) {
                $this->options[$key]=$value;
            }

        }

        function addEnum( $table, $column, $formatter=null) {
            global $DB;
            if ($this->dbh <> '') { $db = $this->dbh; }
            else { $db = $DB; }

            $toAdd = $db->getEnumValues($table, $column);
            if (is_callable($formatter)) {
                $newToAdd=array();
                foreach ($toAdd as $key=>$value) {
                    $key = $formatter($key);
                    if ($key!==null) $newToAdd[$key] = $value;
                }
                $toAdd = $newToAdd;
            }

            $this->addOption($toAdd);
        }

        function setMultiple( $param = '1' ) {
            if ($param === 'single' || $param === 'SINGLE' || !$param) {
                $this->multiple = 0;
            } else {
                $this->multiple = 1;
                if (preg_match('/^[0-9]*$/', $param)) $this->rows = $param;
            }
        }

        function numRows() {
            return count( $this->options );
        }

        function setRows( $rows ) {
            $this->rows = $rows;
            return $rows;
        }

        // Alias addOptions to addOption just for readability
        function addOptions( $arg1, $arg2 = null, $position=null) {
            return $this->addOption( $arg1, $arg2, $position);
        }

        function addOption( $arg1, $arg2 = null, $position=null) {
            if ( is_array($arg1) ) {
                if ($arg2 == null || !$arg2) { $arg2 = 0; }
                foreach( $arg1 as $key=>$value ) {
                    $this->options[$arg2?$value:$key] = $value;
                }
            } else {
                if ( $arg2 === null ) {
//                    echo "---$arg2---";
                    $arg2 = $arg1;
                }
                if (is_null($position)) {
                    $this->options[$arg1] = $arg2;
                } else {
                    $this->options =
                        array_slice($this->options, 0, $position, true) +
                        array($arg1 => $arg2) +
                        array_slice($this->options, $position, count($this->options)-$position, true)
                    ;
                }
            }
        }

        // $which idicates whether the removal is based on key or value
        //    i.e. is $arg1 a list of option values => 0 or a list of option names => 1 to be removed
        //    default is true => values
        // $test defines how the keys are tested
        //    0 -> exact comparison
        //    1 -> case insensitive comparison (default)
        //    2 -> case sensitive regular expression
        //    3 -> case insensitive regular expression
        function removeOption( $arg1, $which = 0, $test = 1 ) {
            if ( !is_array($arg1) ) { $arg1 = array( $arg1 ); }
            pruneHash( $this->options, $arg1, $which, 1, $test );
        }

        function removeOptions( $toRemove ) {
            $this->options = array_diff_key( $this->options, array_flip($toRemove) );
        }

        function removeLookup( $sql, $db='') {
            if ($db == '') {
                global $DB;
                if ($this->dbh <> '') { $db = $this->dbh; }
                else { $db = $DB; }
            }
            $this->removeOption( $db->getColumn($sql) );
        }

        function defaultLookup( $sql, $db=null) {
            if ( is_null($db) ) $db = $this->dbh;
            $newDefaults = $db->getColumn($sql);
            if (!count($newDefaults)) $newDefaults = array( null );
            $this->setDefault( $newDefaults );
        }

        function generateDefault( $rows = '' ) {
            global $WS;
#            if ( $rows > 0 ) $this->setRows( $rows );
            if ( $rows > 1 ) $this->setMultiple($rows);
            if (!isset($WS[$this->name])) {
                return;
            }
            return $this->setDefault($WS[$this->name]);
        }

        function autoDefault($val = 0) {
            $this->autoDefault = $val;
        }

        function setDefault( $def=null ) {
            # if a default has been specified explicitly then don't automatically generate one from the workspace
            $this->autoDefault = 0;
            $this->default = array();
            if ( $def !== null ) {
                if ( is_array( $def ) && $this->multiple ) {
                    foreach( $def AS $key=>$value ) {
//                        echo "adding default $value<BR />";
                        $this->default[$value] = 1;
                    }
                } else {
                    if (is_array( $def ) && isset($def[0])) $def = $def[0];
                    if (!is_array( $def )) $this->default[$def] = 1;
                }
            }
            return $this->default;
        }

        function getDefault() {
            return $this->default;
        }

        function getOptions() {
            return $this->options;
        }

        function setOptions( $options ) {
            $this->options = forceArray($options);
        }

        function setExtra( $stuff ) {
            $this->extra = $stuff;
            return $stuff;
        }

        function setRawOutput( $value ) {
            $this->rawOutput = $value;
            return $value;
        }

        function setName( $name ) {
            $this->name = $name;
            return $name;
        }

        function getName( ) {
            return $this->name;
        }

        function setDb( $dbh ) {
            $this->dbh = $dbh;
            return $dbh;
        }

        function disable( $value=1 ) {
            $this->disabled = $value;
            return $value;
        }

        function addDefault( $value ) {
            $this->default[$value] = 1;
        }

        function addDefaults( $regexp, $which = 'VALUE' ) {
            if ( is_array($regexp) ) {
                foreach( $regexp as $value ) {
                    $this->default[$value] = 1;
                }
            } else {
                $which=strtoupper($which);
                foreach( $this->options as $key=>$value ) {
                    if ( ($which=='OPTION' && preg_match("/$regexp/", $key)) || ($which=='VALUE' && preg_match("/$regexp/", $value)) ) {
                        $this->default[$value] = 1;
                    }
                }
            }
        }

        function removeDefaults( $regexp, $which = 'VALUE' ) {
            if ( is_array($regexp) ) {
                foreach( $regexp as $key=>$value ) {
                    if ( isset($this->default[$value]) ) $this->default[$value]=0;
                }
            } else {
                $which=strtoupper($which);
                foreach( $this->options AS $key=>$value) {
                    if ( ($which=='OPTION' && preg_match("/$regexp/", $key)) || ($which=='VALUE' && preg_match("/$regexp/", $value)) ) {
                        if ( $this->default[$value] ) $this->default[$value]=0;
                    }
                }
            }
        }

        function printDefault() {
            if ($this->autoDefault > -1) $this->generateDefault($this->autoDefault);
            $output = array();
            foreach( $this->options as $key=>$value ) {
                if( isset($this->default[$value]) ) $output[] = htmlspecialchars($key);
            }
            if (count($output)>1) {
                echo '<LI>'.implode("</LI>\n<LI>",$output).'</LI>';
            } else {
                if (count($output)>0) echo $output[0];
            }
        }

        # Switching on autohide means that if only one option is available then no dropdown is shown
        # use autoHide(true) or autoHide(1) to show the remaining value as text (not a dropdown)
        # use autoHide(2) to have just a hidden form field and nothing else showing
        function autoHide($newVal=TRUE) {
            $this->autoHide = $newVal;
        }

        function redisplay( $name, $extra = null, $def = null, $dontEscape = null ) {
            $this->setName($name);
            $this->defaults = array();
            $this->autoDefault = 1;
            $this->display( $extra, $def, $dontEscape );
        }

        function redisplayCheckboxes( $name, $extra = null, $def = null, $dontEscape = null ) {
            $this->setName($name);
            $this->defaults = array();
            $this->autoDefault = 1;
            $this->displayCheckboxes( $extra, $def, $dontEscape );
        }

        // this is for use in non-edit mode
        // it displays the option associated with the default - if there are multiple defaults (ie a multiple select box)
        // then they are displayed as a comma separated list
        // $txtIfEmpty is shown if $defaults is blank. e.g. pass in 'None Found' or somesuch
        function showDefault($txtIfEmpty='') {
            if ($this->autoDefault > -1) $this->generateDefault($this->autoDefault);
            $defaults=array();

            foreach( $this->options AS $key=>$value ) {
                if (!isset($this->default[$value])) continue;
                $defaults[] = $key;
            }
            $defaultString = implode(', ',$defaults);

            if($defaultString == '')
                $defaultString = $txtIfEmpty;

            if ($this->rawOutput) {
                echo $defaultString;
            } else {
                echo htmlspecialchars($defaultString);
            }

            return $defaults;
        }

        function doHidden($showValue=true) {
            $this->isHidden = TRUE;
            $key = array_key_first($this->options);
            $val = $this->options[$key];
            if ($showValue) {
                echo htmlspecialchars($key);
            }
            echo '<INPUT type="hidden" name="'.$this->name.($this->multiple?'[]':'').'" value="'.htmlspecialchars($val).'">';
        }


        function displayDisabled() {
            // box is disabled - show default option and print a hidden form input with the appropriate value
            $this->showDefault();
            $defaults = $this->default;
            if (!$this->multiple) {
                $defaults = array_key_first($defaults);
            }
            formHidden($this->name, $defaults);
        }

        function getTitle() {
            return ucFirst(preg_replace('/[^a-z ]/i','',fromCamelCase($this->name)));
        }

        function display( $extra = null, $def = null, $dontEscape = null ) {
            if ($this->disabled) {
                $this->displayDisabled();
                return 1;
            }

            if ($extra <> null) {
                $extra = $this->extra.' '.$extra;
            } else {
                $extra = isset($this->extra)?$this->extra:'';
            }
            if ($this->autoDefault > 0) $this->generateDefault($this->autoDefault);
            if ($def <> null) $this->setDefault($def);
            if ($dontEscape <> null) $this->setRawOutput($dontEscape);
            if ( is_array($this->default) && ( count($this->default) > 1 ) ) $this->setMultiple('yes');

            if($this->autoHide && !(count($this->options) > 1)) {
                $this->doHidden($this->autoHide==1);
            } else {
                $this->isHidden = FALSE;
                echo '<SELECT ';
                if ($this->multiple) echo 'MULTIPLE="YES" ';
                if ($this->rows <> '') echo "SIZE=\"".$this->rows."\" ";
                echo "$extra ";
                echo 'TITLE="'.$this->getTitle().'" ';
                echo "NAME=\"$this->name";
                if ($this->multiple) echo '[]';
                echo "\">\n";
                $i=1;
                $haveSelected = 0;
                foreach( $this->options as $key=>$value ) {
                    echo "<OPTION ";
                    // handle defaults
                    $checked = '';
                    if ( !$this->multiple ) {
                        if ( (count($this->default)==0 && !$haveSelected) || array_key_exists((string)$value,$this->default) ) {
                            echo 'selected ';
                            $haveSelected = 1;
                        }
                    } elseif ( array_key_exists((string)$value,$this->default) ) {
                        echo 'selected ';
                    }

                    if ($this->rawOutput) {
                        echo 'VALUE="'.$value.'">'.$key."</OPTION>\n";
                    } else {
                        echo 'VALUE="'.htmlspecialchars($value).'">'.htmlspecialchars($key)."&nbsp;&nbsp;&nbsp;</OPTION>\n";
                    }
                    $i++;
                }
                echo "</SELECT>";
            }
        }

        function displayCheckboxes() {
            if ($this->disabled) {
                $this->displayDisabled();
                return 1;
            }

            global $WS;

            static $previousCheckboxNames;
            $extraIdContent = '';
            if (!isset($previousCheckboxNames)) {
                $previousCheckboxNames = array();
            }
            if (isset($previousCheckboxNames[$this->name])) {
                $previousCheckboxNames[$this->name]++;
                $extraIdContent = '_'.$previousCheckboxNames[$this->name];
            } else {
                $previousCheckboxNames[$this->name]=0;
            }

            if ($this->autoDefault > 0) $this->generateDefault($this->autoDefault);

            if ($this->autoHide && !(count($this->options) > 1)) {
                $this->doHidden($this->autoHide==1);
                return(0);
            }

            $input_str = '<span class="checkbox"><input id="input_%s%s_%d" type="%s" name="%s" value="%s" %s %s /><label for="input_%s%s_%d">%s</label></span>'."\n";
            $input_name = $this->name;
            $input_type = 'radio';
            if ($this->multiple) {
                $input_name .= '[]';
                $input_type = 'checkbox';
            }

            $already_checked_one = false;
            $idx=0;
            foreach ($this->options as $key => $value) {
                $idx++;
                $checked = '';
                if ( !$this->multiple ) {
                    if ( isset($this->default[$value]) ) {
                        $checked = ' checked';
                    }
                } elseif ( array_key_exists($value,$this->default) ) {
                    $checked = ' checked';
                }
                printf($input_str,$input_name,$extraIdContent,$idx,$input_type,$input_name,$value,$checked,$this->extra,$input_name,$extraIdContent,$idx,$key);
            }
        }

        function displayPicklist($labels=null) {
            $defaults = array_keys($this->default);
            $defaults = array_combine( $defaults, $defaults );
            $this->removeOptions(array_keys($defaults));
            $selectedList = new formOptionbox('', $defaults);
            formPicklist($this->name,$this,$selectedList,$labels);
        }
    }

?>
