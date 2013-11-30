$(document).ready(function(){
    // restrict group ajax
    $('#restrictgroup,#group').change(function() {
        $.post(OC.filePath('mozilla_sync', 'ajax', 'restrictgroup.php'),
            { restrictgroup: $('#restrictgroup[type=checkbox]').is(':checked'),
            groupselect: $('#groupselect').val()},
            function(){});
    });

    // quota ajax
    $('#syncquotainput').change(function() {
        OC.Notification.hide();
        var my_quota = $('#syncquotainput').val();
        // Empty string is interpreted as quota zero
        if (my_quota === "") {
            my_quota = "0";
        }
        $.post(OC.filePath('mozilla_sync', 'ajax', 'setquota.php'),
            { quota: my_quota },
            function(result){
                if(result.status === "error") {
                    OC.Notification.show(result.data.message);
                }
            });
    });
});

