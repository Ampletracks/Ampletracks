function chemicalInputFavourites_loadHandler(callback) {
    $.ajax('../userLibrary/admin.php',{
        method : 'POST',
        data : {
            type: 'chemical'
        },
        dataType : 'json',
        success : function(data) {
            if (data.status=='ok') {
                callback(new Map(Object.entries(data.data)));
            }
        }
    });
}

function chemicalInputFavourites_saveHandler(name,value) {
    $.ajax('../userLibrary/admin.php',{
        method : 'POST',
        data : {
            mode: 'save',
            name: name,
            value: value,
            type: 'chemical'
        },
        dataType : 'json'
    });
}
