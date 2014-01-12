// Shows a notification and hides it after 3 seconds
showNotification = function(text) {
    clearInterval(notification_timer);
    OC.Notification.hide();
    OC.Notification.show(text);
    var notification_timer = setInterval(function() {
        OC.Notification.hide();
        clearInterval(notification_timer);
    }, 3000);
}
