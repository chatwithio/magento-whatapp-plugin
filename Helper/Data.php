<?php

namespace Tochat\Whatsapp\Helper;

use Magento\Framework\App as App;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;
use Tochat\Whatsapp\Model\ResourceModel\Message\CollectionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Serialize\SerializerInterface;

class Data extends AbstractHelper
{
    const STATUS_SENT = 1;
    const STATUS_PENDING = 2;
    const STATUS_ERROR = 3;
    const TYPE_ORDER = 1;
    const TYPE_CART = 2;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepositoryInterface;

    /**
     * @var SerializerInterface
     */
    protected $serializerInterface;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        App\Helper\Context $context,
        CollectionFactory $collectionFactory,
        OrderRepositoryInterface $orderRepositoryInterface,
        SerializerInterface $serializerInterface,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
        $this->collectionFactory = $collectionFactory;
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        $this->serializerInterface = $serializerInterface;
        parent::__construct(
            $context
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getModuleConfig(string $path, $store_code = null) : ?string
    {
        return $this->scopeConfig->getValue(
            'tochat_whatsapp/' . $path,
            ScopeInterface::SCOPE_STORE,
            $store_code
        );
    }

    /**
     * {@inheritdoc}
     */
    public function isActive() : ?bool
    {
        return (bool)$this->getModuleConfig('general/status');
    }

    /**
     * {@inheritdoc}
     */
    public function getMessages()
    {
        return [
            'processing' => $this->getModuleConfig('order_messages/processing'),
            'canceled' => $this->getModuleConfig('order_messages/canceled'),
            'complete' => $this->getModuleConfig('order_messages/complete'),
            'new' => $this->getModuleConfig('order_messages/new'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplatesId($store_code = null)
    {
        return [
            'processing' => $this->getModuleConfig('automation/template/processing', $store_code),
            'canceled' => $this->getModuleConfig('automation/template/canceled', $store_code),
            'complete' => $this->getModuleConfig('automation/template/complete', $store_code),
            'new' => $this->getModuleConfig('automation/template/new', $store_code),
        ];
    }

    public function getRecentMessage($orderID)
    {
        return $this->collectionFactory->create()
                     ->addFieldToFilter('order_id', $orderID)
                     ->addFieldToFilter('status', self::STATUS_SENT)
                     ->setOrder('created_at', 'DESC')
                     ->getFirstItem();
    }
    
    public function getOrderTelephoneById($orderID)
    {
        $order  = $this->orderRepositoryInterface->get($orderID);
        return $order->getBillingAddress()->getTelephone();
    }

    public function serialize($data)
    {
        return $this->serializerInterface->serialize($data);
    }

    public function unserialize($data)
    {
        return $this->serializerInterface->unserialize($data);
    }

    public function timeElapsedString($datetime, $full = false)
    {
        $now = new \DateTime;
        $ago = new \DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = [
            'y' => __('year'),
            'm' => __('month'),
            'w' => __('week'),
            'd' => __('day'),
            'h' => __('hour'),
            'i' => __('minute'),
            's' => __('second'),
        ];
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) {
            $string = array_slice($string, 0, 1);
        }
        return $string ? implode(', ', $string) . ' ' . __('ago') : __('just now');
    }
}
