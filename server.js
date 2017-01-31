var server = require('http').Server();
var port = 3000;
var io = require('socket.io')(server);

var Redis = require('ioredis');
var redis = new Redis();

redis.subscribe('tracker-channel');

redis.on('message', function(channel, message) {
    console.log(channel);
    console.log(message);
    message = JSON.parse(message);

    io.emit(channel + ':' + message.event, message.data); // test-channel:UserSignedUp
});

server.listen(port, 'localhost', 34, function() {
    console.log("Server listening on port: %s", port);
});