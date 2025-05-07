<?

$primaryFilterIdField = 'dataField.recordTypeId';

$listSql = "
    SELECT
		dataField.*,
		dataFieldType.name AS type,
		# 17=> graph - this is a special case insofar as it DOES have a value, but this can't be displayed on list
		(dataFieldType.hasValue AND dataFieldType.id<>17) AS canDisplayOnList
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
        
        $('table.main tbody input:checkbox').filter('.displayOnList, .displayOnPublicList').on('change',function(){
            var self = $(this);
            var dataFieldId = self.closest('tr').data('id');
            var postData = {
                mode                    : 'update',
                id                      : dataFieldId,
            };
            var fieldName = self.is('.displayOnList') ? 'dataField_displayOnList':'dataField_displayOnPublicList';
            postData[fieldName] =  self.is(':checked')?1:0;
            $.post('admin.php',postData,function(data){
                    if (data!='OK') $.alertable.alert(data);
                })
        });
    });
    </script><?
}

include('../../lib/core/listPage.php');
