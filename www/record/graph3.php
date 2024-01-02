<?

$INPUTS = array(
    '.*' => array(
        'id'      => 'INT',
        'width'   => 'INT',
        'height'  => 'INT',
    )
);


include( '../../lib/core/startup.php' );

$nodeQuerySql = '
    SELECT
        record.id,
        IFNULL(recordData.data,REPEAT(CHAR(63),4)) AS label,
        record.depth AS level,
        recordType.colour AS color,
        record.parentId,
        recordType.name AS title
    FROM
        record
        INNER JOIN recordType on recordType.id=record.typeId
        LEFT JOIN recordData on recordData.recordId=record.id AND recordData.dataFieldId=recordType.primaryDataFieldId
    WHERE
        !record.deletedAt AND
';

function returnAjax( $nodes, $edges ) {
    header('Content-type: application/json');
    echo json_encode([
        'edges' => $edges,
        'nodes' => $nodes
    ],JSON_PRETTY_PRINT);
    exit;
}

$id = ws('id');
$mode = ws('mode');
if ($id && $mode=='extrinsic') {

    $nodeIds = [ $id => 0 ];
    $edges = [];
    $maxDistance = 2;
    $newNodeIds = [$id];
    $incompleteNodes = [];
    
    for ( $distance=1; $distance<=$maxDistance+1; $distance++ ) {
        $DB->returnHash();
        $query = $DB->query('
            SELECT
                relationship.fromRecordId AS `from`,
                relationship.toRecordId AS `to`,
                relationshipLink.description AS `label`
            FROM
                record
                INNER JOIN relationship ON relationship.fromRecordId=record.id
                INNER JOIN record toRecord ON toRecord.id=relationship.toRecordId AND toRecord.lastSavedAt>0 AND toRecord.deletedAt=0
                INNER JOIN relationshipLink ON relationshipLink.id=relationship.relationshipLinkId
            WHERE
                record.id IN (?)
        ',$newNodeIds);

        $newNodeIds=[];
        while($query->fetchInto($row)) {
            // If the node is already in the nodeList then DO include the relationship even though it seems to go beyond the allowed distance
            if ($distance > $maxDistance && !isset($nodeIds[$row['to']])) {
                // The only reason we go beyond maxDistance is to find which nodes on the boundary have relationships outside the distance limit
                // so that we can mark these nodes as being incomplete
                // But don't mark the node as incomplete if the "to" node is already in the node list
                $incompleteNodes[$row['from']]=true;
            } else {
                $edge = $row;
                $edge['id'] = $row['from']>$row['to'] ? ($row['from'].':'.$row['to']) : ($row['to'].':'.$row['from']);
                $edges[] = $edge;

                if (!isset($nodeIds[$row['to']])) {
                    $newNodeIds[] = $row['to'];
                    $nodeIds[$row['to']]=$distance;
                }
            }
        }
        if (!count($newNodeIds)) continue;
    }

    // Now get all the node data
    $DB->returnHash();
    $nodes = $DB->getRows($nodeQuerySql.'
            record.id IN (?)
    ',array_keys($nodeIds));

    foreach( $nodes as &$node ) {
        $node['group'] = isset($incompleteNodes[$node['id']]) ? 'incomplete':'complete';
    }

    returnAjax($nodes,$edges);
    exit;

} else if ($id && $mode=='familial') {

    list( $id, $rootId ) = $DB->getRow('SELECT id, SUBSTRING_INDEX(path,",",1) FROM record WHERE id=?',ws('id'));

    $DB->returnHash();
    $nodeQuery = $DB->query($nodeQuerySql.'
            record.path LIKE ? AND
            ( record.lastSavedAt>0 || record.id=? )
        # Make sure the root node is first
        ORDER BY record.depth ASC
    ',$rootId.',%',$id);

    $edges = $nodes = [];
    while($nodeQuery->fetchInto($nodeData)) {
        if ($nodeData['parentId']) {
            $edges[]=[
                'from'  => $nodeData['parentId'],
                'to'    => $nodeData['id']
            ];
        } 
        unset($nodeData['parentId']);
        $nodes[]=$nodeData;
    }

    returnAjax($nodes,$edges);
    exit;
}
?>
<style>
    html, body {
        margin: 0;
        padding: 0;
    }
    #loading,#graph_familial,#graph_extrinsic {
        width: 100%;
        height: 100%;
        border: 1px solid red;
        position: absolute;
        box-sizing:border-box;
        left: 0;
        top: 0;
    }
    #loading {
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ccc;
    }

    .vis-tooltip div {
        font-family: arial;
        line-height: 150%;
    }

    .vis-tooltip div div.rightArrow, .vis-tooltip div div.leftArrow {
        display: inline-block;
    }
    
    .vis-tooltip div div.rightArrow::before {
        content: '\27A1';
        font-family: arial;
        font-size: 150%;
    }

    .vis-tooltip div div.leftArrow::before {
        content: '\2B05';
        font-family: arial;
        font-size: 150%;
    }

    /* Start of Pure CSS Throbber */
    /* Courtesy of https://cssloaders.github.io/  */
    /*
    MIT License
    Copyright (c) 2020 Vineeth.TR

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in all
    copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
    SOFTWARE.
    */

    .loader {
        width: 48px;
        height: 48px;
        background: #FFF;
        border-radius: 50%;
        display: inline-block;
        position: relative;
        box-sizing: border-box;
        animation: rotation 1s linear infinite;
    }

    .loader::after {
        content: '';  
        box-sizing: border-box;
        position: absolute;
        left: 6px;
        top: 10px;
        width: 12px;
        height: 12px;
        color: #FF3D00;
        background: currentColor;
        border-radius: 50%;
        box-shadow: 25px 2px, 10px 22px;
    }

    @keyframes rotation {
        0% {
            transform: rotate(0deg);
        }
        100% {
            transform: rotate(360deg);
        }
    }

   /* End of Pure CSS Throbber */
 
</style>
<script src="/javascript/jquery.min.js"></script>
<script src="/javascript/vis-network.min.js"></script>

<script>
$(function(){

    var mode = false;

    const networks = {};

    var loadedNodes={};
    var loadedEdges={};

    const loading = $('#loading');

    var lastClickTime=0;
    var lastClickNode=false;
    var lastClickTimeout=false;

    const doubleClickTime = 200;

    function clickHandler(params) {
        console.log('click');
        console.log(params);
        let network = networks[mode].network;
        if ( params.nodes.length==0 && params.edges.length>0 ) {
            let edgeId = params.edges[0];
            let edgeOptions = loadedEdges[edgeId];
            if (edgeOptions) {
                edgeOptions.push(edgeOptions.shift());
                redrawEdges(network,[edgeId]);
                $('.vis-tooltip').css({
                    visibility:'hidden',
                    left:0,
                    top:0
                });
            }
        }

        if (!params.nodes || params.nodes.length==0) return false;

        lastClickNode = params.nodes[0];
        focus( network, lastClickNode );
        
        // On double-click the click event fires twice - ignore the second one
        let now = new Date().getTime();
        let timeSinceLastClick = now-lastClickTime;
        lastClickTime = now;
        if (timeSinceLastClick<doubleClickTime) {
            console.log('Ignoring second click event');
            return;
        }

        // In familial mode we can move straight away
        if (mode=='familial') {
            loadGraph(lastClickNode,mode);
        } else {
            // In extrinsic mode a single click requires an AJAX load, we don't want to do this until we're sure.
            if (!lastClickTimeout) {
                lastClickTimeout = window.setTimeout(function() {
                    lastClickTimeout = false;
                    console.log('Click on node:'+lastClickNode);
                    loadGraph(lastClickNode,mode);
                },1000);
            }
        }
    };

    function doubleclickHandler(params) {
        console.log('Double click');
        if (lastClickTimeout) window.clearTimeout(lastClickTimeout);
        lastClickTimeout = false;
        if (!lastClickNode) return false;

        loadGraph( lastClickNode, mode=='familial'?'extrinsic':'familial' );
        console.log('Doubleclick on '+lastClickNode);
    };

    function setupGraph(mode) {
        let options = {
            edges: {
                smooth: {
                    type: "cubicBezier",
                    forceDirection: "vertical",
                    roundness: 0.4
                },
                widthConstraint: {
                    maximum: 150
                },
                // Use this to increase spacing between nodes
                length: 250,
                arrows: {
                    to: true,
                }
            },
            nodes: {
                widthConstraint: {
                    maximum: 150
                },
                shape: 'box',
                mass: 2,
                borderWidth: 2,
                borderWidthSelected: 3,
            },
            layout: {
                randomSeed: 1
            },
            groups: {
                complete: {
                    shapeProperties: { borderDashes: false }
                },
                incomplete: {
                    shapeProperties: { borderDashes: [4,3] }
                }
            }
        };

        if (mode == 'familial') {
            options.layout.hierarchical =  {
                direction: 'UD'
            };
        } else {
            options.physics = {
                //solver: 'forceAtlas2Based'
            }
        }

        let container = $('#graph_'+mode);
        container.css('left','-3000px');

        let network = new vis.Network(container.get(0), {edges:[],nodes:[]}, options);
        network.on('click',clickHandler);
        network.on("doubleClick", doubleclickHandler);

        let nodes = new vis.DataSet([]);
        let edges = new vis.DataSet([]);
        network.setData({ nodes: nodes, edges: edges });
        networks[mode] = {
            container: container,
            network: network,
            nodes: nodes,
            edges: edges
        };
    }

    function htmlTitle(html) {
        const container = document.createElement("div");
        container.innerHTML = html;
        return container;
    }

    function redrawEdges( network, edgesToRedraw ) {
        for (const edgeId of edgesToRedraw) {
            network.body.data.edges.remove(edgeId);
            let edgeData = loadedEdges[edgeId][0];
            let label = edgeData.label;
            if (loadedEdges[edgeId].length>1) label += ' ('+edgeData.count+'/'+loadedEdges[edgeId].length+')';
            let title = '';
            for (const edge of loadedEdges[edgeId]) {
                let labelEscaped = $('<p>').text(edge.label).html();
                let direction = edgeData.from==edge.from ? 'right':'left';
                title += '<div class="edge"><div class="'+direction+'Arrow"></div> '+labelEscaped+'</div>';
            }
            let newEdge = {
                id:     edgeId,
                from:   edgeData.from,
                to:     edgeData.to,
                label:  label,
                title:  htmlTitle(title)
            }
            network.body.data.edges.add(newEdge);
        }
    }

    function getCentroid( nodes ) {
        let result = {x:0,y:0};
        nodes = Object.values(nodes);
        if (!nodes.length) return result;

        for (const position of nodes ) {
            result.x += position.x;
            result.y += position.y;
        }
        result.x /= nodes.length;
        result.y /= nodes.length;

        return result;
    }

    function getConnectedNodes( nodeId ) {
        let connected = [];
        for( const edge of Object.keys(loadedEdges) ) {
            const [from, to] = edge.split(':');
            if (from==nodeId) connected.push( to );
            else if (to==nodeId) connected.push( from );
        }
        return connected;
    }

    const colourRegex = /^#([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})$/;
    function colourToGrey(hexColor) {
        // Use a regular expression with a capturing group repeated three times
        const match = hexColor.match(colourRegex);
        return (parseInt(match[1], 16) +  parseInt(match[2], 16) +  parseInt(match[3], 16)) / 3;
    }

    function renderGraph( recordId, data, newMode ) {
        let network = networks[newMode];
        let newNodes = [];
        let newEdges = [];
        let edgesNeedingRedraw = [];

        if (newMode=='familial') {
            // In familial mode we can just replace all existing nodes with the new ones
            network.nodes.clear();
            network.edges.clear();
            newNodes = data.nodes;
            newEdges = data.edges;
        } else {
            // In extrinsic mode we keep all existing nodes/edges and just add in any new ones

            // Edges are a bit tricky
            for( const edge of data.edges ) {
                if (!loadedEdges[edge.id]) {
                    edge.count = 1;
                    loadedEdges[edge.id] = [edge];
                    edgesNeedingRedraw.push(edge.id);
                } else {
                    // now see if this version of this edge is in the array
                    let existingEdges = loadedEdges[edge.id];
                    let found = false;
                    for (const existingEdge of existingEdges) {
                        if (existingEdge.from==edge.from && existingEdge.to==edge.to && existingEdge.label==edge.label) {
                            found=true;
                            break;
                        }
                    }
                    if (!found) {
                        edge.count = existingEdges.length+1;
                        existingEdges.push(edge);
                        edgesNeedingRedraw.push(edge.id);
                    }
                }
            }

            // Nodes are easier - except we have to make a stab at where to put them

            let existingNodePositions = network.network.getPositions();
            let networkCentroid = getCentroid(existingNodePositions);
            let existingNodeCount = Object.keys(loadedNodes).length;

            for( const node of data.nodes ) {
                if (loadedNodes[node.id]) {
                    // Check to see if the completeness of this node has changes
                    var existingNode = network.nodes.get(node.id);
                    console.log('existing node: ',existingNode);
                    delete existingNode.x;
                    delete existingNode.y;
                    if (existingNode.group=='incomplete' && node.group=='complete') {
                        existingNode.group = 'complete';
                        network.nodes.update(existingNode);
                    }
                    continue;
                }
                loadedNodes[node.id] = true;

                if (existingNodeCount>0) {
                    let connectedNodes = getConnectedNodes(node.id);
                    if (connectedNodes.length) {
                        // Get the centroid of all the connected nodes
                        let centroid = getCentroid(network.network.getPositions(connectedNodes));
                        console.log('Centroid:',centroid);
                        centroid.x += (centroid.x - networkCentroid.x)/2;
                        centroid.y += (centroid.y - networkCentroid.y)/2;
                        node.x=centroid.x;
                        node.y=centroid.y;
                    } else {
                        // The node is completely separate - find a place for it outside the current network
                        let maxDistance = 0;
                        for (const position of Object.values(existingNodePositions) ) {
                            let distance = (position.x-networkCentroid.x)**2 + (position.y-networkCentroid.y)**2;
                            if (distance > maxDistance) maxDistance = distance;
                        }
                        maxDistance = (maxDistance**0.5) * 1.2;
                        console.log(maxDistance);
                        let randomAngle = Math.random() * Math.PI * 2;
                        node.x = networkCentroid.x + ( Math.cos(randomAngle) * maxDistance );
                        node.y = networkCentroid.y + ( Math.sin(randomAngle) * maxDistance );
                        console.log(node);
                        
                    }

                }
                newNodes.push(node);
            }

        }

        // if the newNodes include the record ID then add this one first and focus on it
        // The graph seems to jump about less if we draw the focussed node in first.
        // Oh... and while we're iterating over the new nodes pick the best text colour
        for( let i=newNodes.length-1; i>=0;  i-- ) {
            // work out the best font colour
            newNodes[i].font = {color: (colourToGrey(newNodes[i].color)>128) ? '#000000' : '#ffffff'}
            console.log(newNodes[i]);

            if (newNodes[i].id==recordId) {
                // Add the node and focus on it
                network.nodes.add([newNodes[i]]);
                focus(network.network,recordId);
                newNodes.splice(i,1);
            }
        }
        focus(network.network,recordId);

        if (mode!=newMode) {
            let container = network.container;

            console.log('flipping networks');
            container.css({
                left: 0,
            });
            container.fadeTo('slow',1);

            if (mode) {
                let oldContainer = networks[mode].container;
                oldContainer.css({
                    left: '-3000px',
                    opacity: 0
                });
            }
        }

        window.setTimeout(function(){
            // if no new nodes added then we just do a move
            if (newNodes.length) network.nodes.add(newNodes);
            if (newEdges.length) network.edges.add(newEdges);
            if (edgesNeedingRedraw.length) redrawEdges( network.network, edgesNeedingRedraw );
        },500);

        loading.fadeOut('fast');

        lastClickNode= false;
        mode=newMode;
    }

    function focus( network, recordId, duration ) {
        console.log('Focus on:',recordId);
        if (!duration) duration = 500;
        network.focus(recordId,{
            locked:true,
            animation: {
                duration: duration,
                easingFunction: 'easeInOutQuad'
            }
        });
        network.selectNodes([recordId],true);
    }

    function loadGraph( recordId, newMode ) {

        // If we are just jumping to another node in the same family tree then there is no need to load anything
        // we can simply recentre the graph on the selected node
        if ( mode=='familial' && newMode == 'familial' ) {
            // the centring will already have been done by the onClick handler
            return;
        }

        loading.fadeIn();

        $.ajax('?',{
            data: {
                mode: newMode,
                id: recordId
            },
            dataType: 'json',
            success: function( data, status ) { renderGraph( recordId, data, newMode ); }
        });
    }

    const queryString = window.location.search;
    const params = new URLSearchParams(queryString);

    const initialRecordId = parseInt(params.get('id'));

    setupGraph('familial');
    setupGraph('extrinsic');
    loadGraph( initialRecordId, params.get('render')=='familial'?'familial':'extrinsic' );
});



</script>

<div id="graph_extrinsic" ></div>
<div id="graph_familial" ></div>
<div id="loading" style="display: none">
    <span class="loader"></span>
</div>
