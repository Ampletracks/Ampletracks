<?

function getRecordDetails( $details ) {
        global $DB;
        
		if (strpos( $details,',')) $where = 'record.path=?';
        else $where = 'record.id=?';
        
        $DB->returnHash();
        return $DB->getRow("
            SELECT
                record.id AS id,
                IFNULL(recordData.data,'-') AS primaryFieldValue,
                record.typeId AS typeId
            FROM record
                INNER JOIN recordType ON recordType.id=record.typeId
                LEFT JOIN recordData ON recordData.dataFieldId=recordType.primaryDataFieldId AND recordData.recordId=record.id
            WHERE
                $where
        ",$details);
}
