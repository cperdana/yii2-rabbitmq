<?php
namespace cperdana\rabbitmq;

use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PhpAmqpLib\Connection\AMQPStreamConnection;



// If this is enabled you can see AMQP output on the CLI
defined('AMQP_DEBUG') or define('AMQP_DEBUG', false);


/**
 * Class Rabbit
 * @package cperdana\rabbitmq
 */
class RabbitPhpAmqpLib
{
    /**
     * @var string Server IP
     */
    public $host = '127.0.0.1';

    /**
     * @var int AMQP port
     */
    public $port = '5672';

    /**
     * @var string Login
     */
    public $user = 'guest';

    /**
     * @var string Password
     */
    public $pass = 'guest';

    /**
     * @var string Virtual host
     */
    public $vhost = '/';


    public $debug = false;

    /**
     * @var string ExchangeName to Send
     */
    public $sendExchangeName = 'exchange_name';
    /**
     * @var string Exchange type to Send
     */
    public $sendExchangeType = 'exchange_type';   // fanout/direct
    /**
     * @var string Queue name to send (to be bind with $sendExchangeName)
     */
    public $sendQueueName = 'send_queue_name';


    /**  
     *@var string queue name to Read
    */
    public $readQueueName = 'read_queue_name';



    private $connection;

    public function rabbit_send($themsg)
    {
        $channel = $this->getChannel('send');

        $messageBody = $themsg;
        $message = new AMQPMessage($messageBody, array('content_type' => 'text/plain', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
        $channel->basic_publish($message, $this->sendExchangeName);
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

    private function getChannel($forReadOrSend){

        $this->connection = new AMQPStreamConnection(
                    $this->host, $this->port, $this->user, $this->pass, $this->vhost);
        $channel = $this->connection->channel();


        if ($forReadOrSend == 'read'){
            // $this->readQueue = $this->readQueueName;
            $channel->queue_declare($this->readQueueName, false, true, false, false);
        
        }else if($forReadOrSend == 'send'){

            // $this->exchange = $this->sendExchangeName;
            // $exchangeType = $this->sendExchangeType;
            // $this->sendQueue = $this->sendQueueName;

            /*
                name: $queue
                passive: false
                durable: true // the queue will survive server restarts
                exclusive: false // the queue can be accessed in other channels
                auto_delete: false //the queue won't be deleted once the channel is closed.
            */
            $channel->queue_declare($this->sendQueueName, false, true, false, false);
            /*
                name: $exchange
                type: direct
                passive: false
                durable: true // the exchange will survive server restarts
                auto_delete: false //the exchange won't be deleted once the channel is closed.
            */
            $channel->exchange_declare($this->sendExchangeName, $this->sendExchangeType, false, true, false);
            $channel->queue_bind($this->sendQueueName, $this->sendExchangeName);

        }


        return $channel;
    }


    public function rabbit_read($callback)
    {
        $channel = $this->getChannel('read');


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
        $channel->basic_consume($this->readQueueName, $consumerTag, false, false, false, false, $callback);


        // Loop as long as the channel has callbacks registered
        while (count($channel->callbacks)) {
            $channel->wait();
        }
    }

}