<?

$INPUTS = array(
    '.*' => array(
        'type' => 'TEXT',
    ),
    'save' => array(
        'name' => 'TEXT',
        'value' => 'TEXT',
    ),
);
include('../../lib/core/startup.php');

header('Content-type: application/json');

$type = ws('type');
$result = [
    'status' => 'ok'
];

// Doing this in case we add support for more types later
if (!in_array($type,['chemical'])) {
    $result['status'] = 'error';
    $result['message'] = 'Invalid type';
} else if (ws('mode')=='save') {
    $value = ws('value');
    $name = trim(ws('name'));
    if ($name=='') {
        $result['status'] = 'error';
        $result['message'] = 'No name supplied';
    } else {
        $where = [
            'type'      => $type,
            'userId'    => $USER_ID,
            'name'      => $name
        ];
        if ($value==='') {
            $DB->delete('userLibrary',$where);
            $result['message'] = 'Item deleted';
        } else {

            $replaceResult = $DB->replace( 'userLibrary', $where, [ 'value' => $value ] );
            if ($replaceResult=='updated') $result['message'] = 'Item updated';
            else if ($replaceResult=='inserted') $result['message'] = 'Item created';
            else {
                $result['status'] = 'error';
                $result['message'] = 'Database error encountered during save';
            }
        }
    }
} else {
    $result['data'] = $DB->getHash('
        SELECT name, value
        FROM
            userLibrary
        WHERE
            userId=?
    ',$USER_ID);
}

echo json_encode($result);
exit;