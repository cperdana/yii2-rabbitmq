<?php
namespace cperdana\rabbitmq;

use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Class Rabbit
 * @package cperdana\rabbitmq
 */
class RabbitPhpAmqpLib
{
    
    public $host = '127.0.0.1';
    public $port = '5672';
    public $user = 'guest';
    public $pass = 'guest';
    public $vhost = '/';
    public $debug = false;

    public $sendExchangeName = 'cmerp.wgl.wgm';
    public $sendExchangeType = 'fanout';   // fanout/direct
    public $sendQueueName = 'wg_manager';

    public $readQueueName = 'wg_manager';


    private $exchange;
    private $sendQueue;
    private $readQueue;
    private $connection;




    public function rabbit_send($themsg)
    {
        $channel = $this->getParam('send');

        $messageBody = $themsg;
        $message = new AMQPMessage($messageBody, array('content_type' => 'text/plain', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
        $channel->basic_publish($message, $this->exchange);
        $channel->close();
        $this->connection->close();
    }

    public function rabbit_sendAck($message){
        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
        // Send a message with the string "quit" to cancel the consumer.
        if ($message->body === 'quit') {
            $message->delivery_info['channel']->basic_cancel($message->delivery_info['consumer_tag']);
        }    
    }

    private function getParam($forReadOrSend){
        define('HOST',  '127.0.0.1');
        define('PORT', 5672);
        define('USER', 'guest');
        define('PASS', 'guest');
        define('VHOST', '/');

         //If this is enabled you can see AMQP output on the CLI
        define('AMQP_DEBUG', false);


        $this->connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
        $channel = $this->connection->channel();


        if ($forReadOrSend == 'read'){
            $this->readQueue = \Yii::$app->params['rabbitReadQueueName'];
            $channel->queue_declare($this->readQueue, false, true, false, false);
        
        }else if($forReadOrSend == 'send'){

            $this->exchange = \Yii::$app->params['rabbitSendExchangeName'];
            $exchangeType = \Yii::$app->params['rabbitSendExchangeType'];
            $this->sendQueue = \Yii::$app->params['rabbitSendQueueName'];

            /*
                name: $queue
                passive: false
                durable: true // the queue will survive server restarts
                exclusive: false // the queue can be accessed in other channels
                auto_delete: false //the queue won't be deleted once the channel is closed.
            */
            $channel->queue_declare($this->sendQueue, false, true, false, false);
            /*
                name: $exchange
                type: direct
                passive: false
                durable: true // the exchange will survive server restarts
                auto_delete: false //the exchange won't be deleted once the channel is closed.
            */
            $channel->exchange_declare($this->exchange, $exchangeType, false, true, false);
            $channel->queue_bind($this->sendQueue, $this->exchange);

        }


        return $channel;
    }


    public function rabbit_read($callback)
    {
        $channel = $this->getParam('read');


        /*
            queue: Queue from where to get the messages
            consumer_tag: Consumer identifier
            no_local: Don't receive messages published by this consumer.
            no_ack: Tells the server if the consumer will acknowledge the messages.
            exclusive: Request exclusive consumer access, meaning only this consumer can access the queue
            nowait:
            callback: A PHP Callback
        */
        $consumerTag = 'consumer';
        $channel->basic_consume($this->readQueue, $consumerTag, false, false, false, false, $callback);


        // Loop as long as the channel has callbacks registered
        while (count($channel->callbacks)) {
            $channel->wait();
        }
    }

}