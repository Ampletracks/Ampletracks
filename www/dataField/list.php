<?

$primaryFilterIdField = 'dataField.recordTypeId';

$listSql = "
    SELECT dataField.*, dataFieldType.name AS type, dataFieldType.hasValue
    FROM dataField
        INNER JOIN dataFieldType ON dataFieldType.id=dataField.typeId
    WHERE !deletedAt
    ORDER BY orderId ASC
";

$extraScripts = array('/javascript/jquery-ui.justDraggable.min.js');

function extraPageContent(){
    global $exampleDataFieldId;
    if (!canDo('edit',$exampleDataFieldId)) return;
    ?><script>
    $(function(){
        $('table.main tbody').sortable({
            update:function(e,ui){
                $.post('admin.php',{
                    mode                : 'update',
                    id                  : ui.item.data('id'),
                    dataField_orderId   : ui.item.prevAll().length+1
                },function(data){
                    if (data!='OK') $.alertable.alert(data);
                })
            }
        });
        
        $('table.main tbody input:checkbox.displayOnList').on('change',function(){
            var self = $(this);
            var dataFieldId = self.closest('tr').data('id');
            $.post('admin.php',{
                mode                    : 'update',
                id                      : dataFieldId,
                dataField_displayOnList : self.is(':checked')?1:0
            },function(data){
                    if (data!='OK') $.alertable.alert(data);
                })
        });
    });
    </script><?
}

include('../../lib/core/listPage.php');
