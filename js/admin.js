$(document).ready(function(){
    $('#mozilla_sync').change(function() {
        $.post(OC.filePath('mozilla_sync','ajax','restrictgroup.php'), { restrictgroup: $('#restrictgroup').val() }, function(){});
    });
});

