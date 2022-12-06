<?

$INPUTS = array(
    '.*' => array(
        'id'      => 'INT',
        'width'   => 'INT',
        'height'  => 'INT',
    )
);


include( '../../lib/core/startup.php' );

list( $id, $rootId ) = $DB->getRow('SELECT id, SUBSTRING_INDEX(path,",",1) FROM record WHERE id=?',ws('id'));

$DB->returnHash();
$nodeQuery = $DB->query('
    SELECT
        record.id,
        IFNULL(recordData.data,REPEAT(CHAR(63),4)) AS label,
        record.depth AS level,
        record.parentId,
        IF(record.id=?,"#FFBBBB","#97C2FC") AS color
    FROM
        record
        INNER JOIN recordType on recordType.id=record.typeId
        LEFT JOIN recordData on recordData.recordId=record.id AND recordData.dataFieldId=recordType.primaryDataFieldId
    WHERE
        !record.deletedAt AND
        record.path LIKE ? AND
        ( record.lastSavedAt || record.id=? )
',$id, $rootId.',%',$id);

$edgeJSON = $nodeJSON = '';
while($nodeQuery->fetchInto($nodeData)) {
    if ($nodeData['parentId']) {
        $edgeJSON.=sprintf('{from:%d,to:%d},',$nodeData['parentId'],$nodeData['id']);
        unset($nodeData['parentId']);
    } 
    $nodeJSON.=json_encode($nodeData).',';
}
if (strlen($edgeJSON)) $edgeJSON = '['.substr($edgeJSON,0,-1).']';
else $edgeJSON = '[]';
if (strlen($nodeJSON)) $nodeJSON = '['.substr($nodeJSON,0,-1).']';
else $nodeJSON = '[]';

?>
<style>
    html, body {
        margin: 0;
        padding: 0;
    }
    #recordGraph {
        width: 100%;
        height: 100%;
    }
</style>
<script src="/javascript/jquery.min.js"></script>
<script src="/javascript/vis-network.min.js"></script>
<script>
    
$(function(){
    var nodes = <?=$nodeJSON?>;
    var edges = <?=$edgeJSON?>;
    var network = null;

    var container = document.getElementById("recordGraph");
    var data = {
        nodes: nodes,
        edges: edges
      };
      
    var options = {
        edges: {
            smooth: {
                type: "cubicBezier",
                forceDirection: "vertical",
                roundness: 0.4
            }
        },
        layout: {
          hierarchical: {
            direction: 'UD'
          }
        }
      };
      
    network = new vis.Network(container, data, options);
    var focus = function(){
        network.focus(<?=json_encode($id)?>,{scale:0.9});
        network.off('afterDrawing',focus);
    };
    network.on('afterDrawing',focus);
    
    // add event listeners
    network.on("click", function(params) {
        var id = params.nodes.pop();
        top.location.href='admin.php?id='+id;
    });

    window.resizeHandler = function(){
        console.log('hello');
        network.on('afterDrawing',focus);
    };
});
</script>

<div id="recordGraph"></div>
