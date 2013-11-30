$(document).ready(function(){
	// delete storage ajax
	$('#deletestorage').click(function() {
		$.post(OC.filePath('mozilla_sync','ajax','deletestorage.php'), {},
			function(result){
				if(result) {
					OC.Notification.show(t('mozilla_sync', result.data.message));
				}
			});
	});
});
