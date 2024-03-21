<a title="<?cms('Open record in new window',0)?>" target="_blank" class="openRecord" href="admin.php?id=<? wsp('id')?>">link</a>
<?
global $dataFields;
foreach($dataFields as $id => $dataField) {
    if (!$dataField->displayOnList()) continue;
    $dataField->displayRow(true);
}
?>
