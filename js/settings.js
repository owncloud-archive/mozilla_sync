$(document).ready(function(){
	// delete storage ajax
	$('#deletestorage').click(function() {
		$.post(OC.filePath('mozilla_sync','ajax','deletestorage.php'), {},
			function(result){
				if(result) {
					OC.Notification.show(result.data.message);
				}
			});
	});

    // sync email ajax
    $('#syncemailinput').change(function() {
        var my_email = $('#syncemailinput').val();
        $.post(OC.filePath('mozilla_sync', 'ajax', 'setemail.php'),
            { email: my_email },
            function(result){
                showNotification(result.data.message);
            });

    });
});
