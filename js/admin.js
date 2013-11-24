$(document).ready(function(){
    $('#mozilla_sync').change(function() {
        $.post(OC.filePath('mozilla_sync','ajax','admin.php'), 
            { restrictgroup: $('#restrictgroup[type=checkbox]').is(':checked'), 
              groupselect: $('#groupselect').val(),
              quotalimit: $('#quotalimitinput').val() }, 
            function(){});
        //ToDo: add a validation for the limit to be integer
    });
});

