<?
global $dataFields;
foreach($dataFields as $id => $dataField) {
    if (!$dataField->displayOnList()) continue;
    $dataField->displayRow(true);
}
?>
