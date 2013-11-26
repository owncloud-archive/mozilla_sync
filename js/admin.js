$(document).ready(function(){
    // restrict group ajax
    $('#restrictgroup,#group').change(function() {
        $.post(OC.filePath('mozilla_sync','ajax','restrictgroup.php'), 
            { restrictgroup: $('#restrictgroup[type=checkbox]').is(':checked'), 
            groupselect: $('#groupselect').val()}, 
            function(){});
    });
    
    // quota ajax
    $('#syncquotainput').change(function() {
        OC.Notification.hide();
        $.post(OC.filePath('mozilla_sync','ajax','setquota.php'), 
            { quota: $('#syncquotainput').val() }, 
            function(result){
                if(result.status === "error") {
                    OC.Notification.show(t('admin', result.data.message));
                }
            });
    });
});

