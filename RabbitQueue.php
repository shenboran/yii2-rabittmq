<?php
namespace shenboran\rabbitmq;

use AMQPChannel;
use AMQPConnection;
use AMQPExchange;
use AMQPQueue;
use Exception;
use yii\base\Component;

/**
 * 实现一个rabbmitMQ队列对象
 *
 * @author shenboran
 *        
 */
abstract class RabbitQueue extends Component
{

    /**
     * 交换机名称
     *
     * @var string
     */
    protected $_exchangeName = 'exchange';

    /**
     * 队列名称
     *
     * @var string
     */
    protected $_queueName = 'queue1';

    /**
     * 路由
     *
     * @var string
     */
    protected $_routeKey = 'router';

    /**
     * 交换机处理类型
     *
     * @var string
     */
    protected $_exchangeType = AMQP_EX_TYPE_DIRECT;

    /**
     * 链接对象
     *
     * @var unknown
     */
    protected $_connection;

    /**
     * 频道
     *
     * @var unknown
     */
    protected $_channel;

    /**
     * 交换机
     *
     * @var unknown
     */
    protected $_exchange;

    /**
     * 队列
     *
     * @var unknown
     */
    protected $_queue;

    /**
     * 基本配置信息
     *
     * @var array
     */
    protected $_config = array(
        'host' => '127.0.0.1',
        'port' => '5672',
        'vhost' => '/',
        'login' => 'guest',
        'password' => 'guest'
    );

    /**
     * 构造函数，依次创建通道，交换机，队列
     */
    public function __construct()
    {
        try {
            $config = \Yii::$app->params['rabbitMQ'] ?? $this->_config; //
            $this->_connection = new AMQPConnection($config);
            if (! $this->_connection->connect()) {
                throw new \Exception('connect failed');
            }
            $this->createChannel();
            $this->createExchange();
            $this->createQueue();
        } catch (Exception $e) {
            throw new \Exception('connect failed');
        }
    }

    /**
     * 创建通道
     */
    protected function createChannel()
    {
        $this->_channel = new AMQPChannel($this->_connection);
    }

    /**
     * 创建交换机
     *
     * @param string $exchangeName
     *            交换机名称
     * @param string $exchangeType
     *            交换机类型 AMQP_EX_TYPE_DIRECT -直连 AMQP_EX_TYPE_FANOUT-广播 AMQP_EX_TYPE_TOPIC-关联路由
     */
    public function createExchange()
    {
        $this->_exchange = new AMQPExchange($this->_channel);
        $this->_exchange->setName($this->_exchangeName);
        $this->_exchange->setType($this->_exchangeType);
        $this->_exchange->setFlags(AMQP_DURABLE);
        $this->_exchange->declareExchange();
    }

    /**
     * 创建队列
     *
     * @param string $q_name
     *            队列名称
     * @param string $route_key
     *            路由名称
     */
    public function createQueue()
    {
        $this->_queue = new AMQPQueue($this->_channel);
        $this->_queue->setName($this->_queueName);
        $this->_queue->setFlags(AMQP_DURABLE);
        $this->_queue->declareQueue();
        $this->_queue->bind($this->_exchange->getName(), $this->_routeKey);
    }

    public function public($data)
    {
        $message = is_array($data) ? json_encode($data) : $data;
        $this->_exchange->publish($message, $this->_routeKey);
        $this->_connection->disconnect();
    }

    /**
     * 队列实际处理的方法
     *
     * @param object $envelope
     * @param object $queue
     */
    abstract public function proccess($envelope, $queue);

    /**
     * 消耗队列当中的元素
     */
    public function consume($auto_ack = false)
    {
        while (true) {
            if ($auto_ack) {
                $this->_queue->consume(array(
                    $this,
                    'proccess'
                ), AMQP_AUTOACK);
            } else {
                $this->_queue->consume(array(
                    $this,
                    'proccess'
                ));
            }
        }
    }
}

?>