# yii2-rabittmq
非常方便使用rabbitMQ队列(其实只要amqp支持的都可以)，使用yii2框架

# 使用前
*  php7
*  安装amqp扩展

# 安装
在项目文件composer.json 中加入

``` 
"require" : {
        ....
		"shenboran/yii2-rabbitmq" : "dev-master"
	},

```
# 设置rabbitMQ服务配置信息

在params.php 文件中申明地址

```
    'rabbitMQ' => [
        'host' => "127.0.0.1",
        'port' => '5672',
        'login' >= 'guest',
        'password' => "guest",
        'vhost' => "/"
    ]
```

# 实现自己队列服务层

* 新建文件 SMSQueue.php 

```php
<?php
namespace common\component\queue;

use common\utils\SmsUtil;
use shenboran\rabbitmq\RabbitQueue;

class SMSQueue extends RabbitQueue 
{

    /**
     * 交换机名称  定义自己的交换机
     * 
     * @var string
     */
    protected $_exchangeName = 'exchange'; 

    /**
     * 队列名称
     *
     * @var string 定义你自己的队列名称
     */
    protected $_queueName = 'q_sim';

    /**
     * 路由 
     *
     * @var string  定义你自己的路由名称
     */
    protected $_routeKey = 'sendmsg';
    
    /**
     * 路由 
     *
     * @var string  定义你自己的交换机的转发类型 默认是AMQP_EX_TYPE_DIRECT
     */
    protected $_exchangeType = AMQP_EX_TYPE_DIRECT;
    
    

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 实现抽象方法 
     * 用来处理队列元素的 逻辑
     */
    public function proccess($envelope, $queue)
    {
        
        $msg = $envelope->getBody();
        $result = json_decode($msg, true); //获取队列元素信息
        if (YII_ENV_PROD) { // 真实环境采取发送短信
            $result = SmsUtil::sendCode($result['m'], $result['v']);//自己的业务逻辑
            if ($result) { 
                $queue->ack($envelope->getDeliveryTag());//手动发送ack 确认队列中的元素已经消耗掉了
            }else{
            		//发送失败的逻辑 
            }
        } else {
            $queue->ack($envelope->getDeliveryTag());//手动发送ack 确认队列中的元素已经消耗掉了
        }
    }
}
```

# 注册你的入列compotent

在main.php 中注册下你的队列服务层 

```php

<?php
return [
    
    'components' => [
 
        'smsQueue' => [ 
            'class' => 'common\component\queue\SMSQueue'  //一个用于发送短信的队列
        ],
        'wechatPMPQueue' => [
            'class' => 'common\component\queue\WechatPMPQueue' //一个用于发送微信模板推送的队列
        ]
    ]
];

```

# 发送入队信息


```php

    //支持 数组 和字符串   其实最后都转化成字符串了 
    $data=['m'=>'13888888888','v'=>'1234'];
    \Yii::$app->smsQueue->public($data);
    
    \Yii::$app->wechatPMPQueue->public($data);
```

# 消耗队列消息

```php

    // 默认情况下 不会自动确认队列元素是否消耗掉(推荐)
    \Yii::$app->smsQueue->consume();
    
    // 自动发送ack 用来消耗队列中的元素
    \Yii::$app->smsQueue->consume(true);
```







