<?

// Generic lookup for suggested textbox data fields
// Create a suggestedTextbox.php in the same directory as the data entry form containing nothing but
// include(LIB_DIR.'/suggestedTextboxSearch.php');

$INPUTS = array(
    '.*' => array(
        'dataFieldId' => 'INT',
        'ttsSearch' => 'TEXT',
    ),
);

include( '../../lib/core/startup.php' );
include_once( LIB_DIR.'/dataField.php');

list( $dataFieldParams, $recordTypeId ) = $DB->getRow('SELECT parameters, recordTypeId FROM dataField WHERE id = ?', ws('dataFieldId'));
DataField::unserializeParameters($dataFieldParams);
$searchResults = array();
$predefinedOptions = explode("\n", $dataFieldParams['predefinedOptions']);
foreach($predefinedOptions as $predefinedOption) {
    $predefinedOption = trim($predefinedOption);
    if(stripos($predefinedOption, ws('ttsSearch')) !== false) {
        $searchResults[$predefinedOption] = array(
            'value' => $predefinedOption,
            'item' => $predefinedOption,
        );
    }
}

// Search mode means that the input is being displayed in a search context. This meanse
// That we show the user all the existing values regardless of whether user-contributed additions are
// currently allowed. This is because, the setting may have changed and user-contributed values
// might have been allowed in the past
if ( ws('mode')=='search' || ( $dataFieldParams['allowAdditions'] && $dataFieldParams['suggestAdditions'] )) {
    $values = DataField::findValuesUserCanSee(
        ws('dataFieldId'),
        'recordData.data LIKE "%'.$DB->escape(ws('ttsSearch')).'%" AND',
        null,
        $recordTypeId
    );
    $recordDataResults = [];
    foreach( $values as $value ) {
        $recordDataResults[ $value ] = [ 'value' => $value, 'item' => $value ];
    }
    //$LOGGER->log("dFI ".ws('dataFieldId').", sT ->".ws('searchText')."<- gets:\n".print_r($recordDataResults, true));

    $searchResults = array_merge($searchResults, $recordDataResults);
}

ksort($searchResults);
$searchResults = array_values($searchResults);

echo json_encode($searchResults);
exit;
