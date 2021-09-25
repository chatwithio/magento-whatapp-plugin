<?php
namespace Tochat\Whatsapp\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tochat\Whatsapp\Model\MessageFactory;
use Tochat\Whatsapp\Helper\Data as DataHelper;

class AfterOrderSave implements ObserverInterface
{
    /**
     * @var MessageFactory
     */
    protected $messageFactory;

    /**
     * @var DataHelper
     */
    protected $dataHelper;
    
    public function __construct(
        MessageFactory $messageFactory,
        DataHelper $dataHelper
    ) {
        $this->messageFactory = $messageFactory;
        $this->dataHelper = $dataHelper;
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        $model = $this->messageFactory->create();

        if (in_array($order->getState(), ['new', 'processing', 'canceled', 'complete'])) {

            $messages = $this->dataHelper->getTemplatesId();

            $model->setOrderId($order->getId())
                ->setExtradata($this->dataHelper->serialize([
                    'increment_id' => $order->getIncrementId(),
                    'status' => $order->getState(),
                ]))
                ->setStatus(DataHelper::STATUS_PENDING)
                ->setMessage($messages[$order->getState()])
                ->save();
        }

        return $this;
    }
}
