Pusher.logToConsole = true;

var pusher = new Pusher('d3f96738bc8f4a369b91', {
    cluster: 'us2'
});

var channel = pusher.subscribe('general');
channel.bind('message', function(data) {
    alert(JSON.stringify(data));
});