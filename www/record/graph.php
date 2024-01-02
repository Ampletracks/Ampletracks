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
    #recordGraph,#recordGraph2 {
        width: 49%;
        height: 100%;
        border: 1px solid red;
        position: absolute;
        left: 10px;
    }
    #recordGraph2 {
        left: -1000px;
    }

</style>
<script src="/javascript/jquery.min.js"></script>
<script src="/javascript/vis-network.min.js"></script>
<script>

var network,network2;
$(function(){
    var nodes = <?=$nodeJSON?>;
    var edges = <?=$edgeJSON?>;

    var container = document.getElementById("recordGraph");
    var container2 = document.getElementById("recordGraph2");

    var data = {
        nodes: nodes,
        edges: edges
      };
    console.log(edges);

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

    for( edge of edges ) {
        edge.to = nodes[parseInt(Math.random()*nodes.length)].id;
    }
    network2 = new vis.Network(container2, data, options);

    var focus = function(){
        network.focus(<?=json_encode($id)?>,{scale:0.9});
        network.off('afterDrawing',focus);
    };
    network.on('afterDrawing',focus);
    
    // add event listeners
    var lastClickTime=0;
    var lastMove=false;
    var lastNodeId=false;
    network.on("click", function(params) {
        if (!params.nodes || params.nodes.length==0) return false;

        // On double-click the click event fires twice - ignore the second one
        let now = new Date().getTime();
        let timeSinceLastClick = now-lastClickTime;
        lastClickTime = now;
        if (timeSinceLastClick<1000) {
            console.log('Ignoring second click event');
            return;
        }

        // top.location.href='admin.php?id='+id;
        lastNodeId = params.nodes.pop();
        lastMove = network.getPosition(lastNodeId);
        
        network.moveTo({
            position: lastMove,
            animation: {
                duration: 500,
                easingFunction: 'easeInOutQuad'
            }
        });

        network2.moveTo({
            position: network2.getPosition(lastNodeId),
            animation: {
                duration: 500,
                easingFunction: 'easeInOutQuad'
            }
        });

    });

    network.on("doubleClick", function(params) {
        console.log('Double Click:',params);
        $('#recordGraph').fadeOut('fast');
        $('#recordGraph2').css('left','10px').fadeIn('fast');
    });

    window.resizeHandler = function(){
        network.on('afterDrawing',focus);
    };
});
</script>

<div id="recordGraph2"></div>
<div id="recordGraph"></div>
