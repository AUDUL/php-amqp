--TEST--
Orphaned envelope with purged queue (https://github.com/php-amqp/php-amqp/issues/327)
--SKIPIF--
<?php if (!extension_loaded("amqp")) print "skip"; ?>
--FILE--
<?php
$conn = new AMQPConnection();
$conn->connect();
$channel = new AMQPChannel($conn);

// Create a queue
$queue = new AMQPQueue($channel);
$queue->setName(uniqid());
$queue->setFlags(AMQP_NOPARAM);
$queue->declareQueue();

// Publish two messages to the queue
$exchange = new AMQPExchange($channel);
$exchange->setFlags(AMQP_PASSIVE);
$exchange->publish(uniqid(), $queue->getName());
$exchange->publish(uniqid(), $queue->getName());

// Consume a single message (will actually dequeue both messages from rabbitmq into client)
$queue->consume(function ()
{
    return false;
});

var_dump(gettype($queue->getConsumerTag()));
var_dump(count($channel->getConsumers()));

// At this point the AMQP client has already dequeued both messages locally - the following
// methods do not clear the client queue
$queue->cancel();
$queue->purge();

var_dump(gettype($queue->getConsumerTag()));
var_dump(count($channel->getConsumers()));

// Following consume will throw orphaned envelope because we are consuming with a different
// consumer tag (auto generated since not specified) and the library (incorrectly) validates
// that the consumer tag which dequeued the last message matches the current consumer tag
// using the client
$conn->setReadTimeout(1.0);
$queue->consume(function ()
{
    return false;
});
var_dump(count($channel->getConsumers()));
?>
==DONE==
--EXPECT--
string(6) "string"
int(1)
string(4) "NULL"
int(1)
int(2)
==DONE==