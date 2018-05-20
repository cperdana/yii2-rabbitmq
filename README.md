# yii2-rabbitmq
Yii2 Rabbimmq

## Installation
add
```
"cperdana/yii2-rabbitmq": "*"
```
to the require section of your composer.json file.

## Used PhpAmqpLib
add component in config file
```
'rabbit' => [
	'class' => 'cperdana\rabbitmq\RabbitPhpAmqpLib',
	'host' => '127.0.0.1',
	'port' => 5666,
	'user' => 'user_login',
	'pass' => 'user_pass',
	'vhost' => '/',
	'readQueueName' => 'read_queue',

	'sendExchangeName' => 'exchange_name',
	'sendExchangeType' => 'fanout', // fanout/direct
	'sendQueueName' => 'send_queue'
],
```

add console command
```
use PhpAmqpLib\Message\AMQPMessage;
use cperdana\rabbitmq\RabbitPhpAmqpLib;

class MsgqueController extends \yii\console\Controller
{
    private function execute($message){
        echo $message->body;
    
        $rabbit = \Yii::$app->rabbit;
        $rabbit->sendAck($message);    
    }

    public function actionSend($themsg){
        $rabbit = \Yii::$app->rabbit;
        $rabbit->send($themsg);
    }

    public function actionRead(){

        $callback = function($message){
            $this->execute($message);            
        };

        $rabbit = \Yii::$app->rabbit;
        $rabbit->read($callback);
    }
}
```

as consumer
```
php yii msgque/read
```

as publisher
```
php yii msgque/send "this is the message to sent to rabbitmq"
```