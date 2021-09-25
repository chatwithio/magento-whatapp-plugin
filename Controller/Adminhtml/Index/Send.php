<?php
namespace Tochat\Whatsapp\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\InputException;
use Tochat\Whatsapp\Model\MessageFactory;
use Psr\Log\LoggerInterface;
use Tochat\Whatsapp\Helper\Data as DataHelper;

class Send extends Action
{

    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Magento_Sales::sales_order';

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var MessageFactory
     */
    protected $messageFactory;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var DataHelper
     */
    protected $dataHelper;

    public function __construct(
        Action\Context $context,
        OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        MessageFactory $messageFactory,
        DataHelper $dataHelper,
        LoggerInterface $logger
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->messageFactory = $messageFactory;
        $this->orderRepository = $orderRepository;
        $this->dataHelper = $dataHelper;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * Cancel order
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $requestJson = $this->resultJsonFactory->create();
        $response = [
            'error' => false
        ];

        try {
            $data = $this->getRequest()->getPostValue();

            $model = $this->messageFactory->create();

            $order = $this->orderRepository->get($data['order_id']);

            $model->setOrderId($data['order_id'])
                ->setExtradata($this->dataHelper->serialize([
                    'increment_id' => $order->getIncrementId(),
                    'status' => $order->getState(),
                ]))
                ->setSentOn(date('Y-m-d H:i:s'))
                ->setMessage($data['message'])
                ->save();

            $history = $order->addStatusHistoryComment(
                __("WhatsApp:"). $data['message'],
                $order->getState()
            );
            $history->setIsVisibleOnFront(false);
            $history->setIsCustomerNotified(false);
            $history->save();

            $response = $data;

        } catch (\Exception $e) {
            $response = [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }

        return $requestJson->setData($response);
    }
}
