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

$dataFieldParams = $DB->getValue('SELECT parameters FROM dataField WHERE id = ?', ws('dataFieldId'));
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

$recordDataResults = $DB->getHash('
    SELECT DISTINCT
        data,
        data AS value,
        data AS item
    FROM recordData
    WHERE dataFieldId = ?
    AND data LIKE ?
    ORDER BY data
', ws('dataFieldId'), '%'.ws('ttsSearch').'%');
//$LOGGER->log("dFI ".ws('dataFieldId').", sT ->".ws('searchText')."<- gets:\n".print_r($recordDataResults, true));

$searchResults = array_merge($searchResults, $recordDataResults);
ksort($searchResults);
$searchResults = array_values($searchResults);

echo json_encode($searchResults);
exit;
