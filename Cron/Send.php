<?php

namespace Tochat\Whatsapp\Cron;

use \Psr\Log\LoggerInterface;
use Tochat\Whatsapp\Model\ResourceModel\Message\CollectionFactory;
use Tochat\Whatsapp\Helper\Api;
use Tochat\Whatsapp\Helper\Data as DataHelper;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\CurrencyFactory;

class Send
{

    /**
     * @var array
     */
    private $locale = [];

    /**
     * @var array
     */
    private $currency = [];

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var Api
     */
    protected $api;

      /**
     * @var DataHelper
     */
    protected $dataHelper;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepositoryInterface;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * @var CurrencyFactory
     */
    protected $currencyFactory;


    public function __construct(
        LoggerInterface $logger,
        CollectionFactory $collectionFactory,
        Api $api,
        DataHelper $dataHelper,
        OrderRepositoryInterface $orderRepositoryInterface,
        StoreManagerInterface $storeManagerInterface,
        CurrencyFactory $currencyFactory
    ) {
        $this->api = $api;
        $this->dataHelper = $dataHelper;
        $this->logger = $logger;
        $this->collectionFactory = $collectionFactory;
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->currencyFactory = $currencyFactory;
    }

    public function getLocale($storeId)
    {
        if (!isset($this->locale[$storeId])) {
            $store = $this->storeManagerInterface->getStore($storeId);
            $this->locale[$storeId] = $store->getLocaleCode($store);
        }
        return $this->locale[$storeId];
    }

    public function getCurrency($storeId)
    {
        if (!isset($this->currency[$storeId])) {
            $store = $this->storeManagerInterface->getStore($storeId);
            $currency = $this->currencyFactory->create()->load($store->getCurrentCurrencyCode()); 
            $this->currency[$storeId] = $currency->getCurrencySymbol();
        }
        return $this->currency[$storeId];
    }

    public function execute()
    {
        if (!$this->api->isActive()) {
            return;
        }

        $messesges = $this->collectionFactory->create()
                    ->addFieldToFilter('status', DataHelper::STATUS_PENDING);

        if(!$messesges->getSize()) return;

        //Fetch Templates
        $templates = [];
        $response = $this->api->getTemplates();
        if (isset($response->waba_templates)) {
            foreach($response->waba_templates as $template){
                if($template->status == 'approved'){
                    $templates[$template->language][$template->name] = $template->namespace;
                }
            }
        }

        $orderTemps = $this->dataHelper->getTemplatesId();

        // print_r($orderTemps);

        //$this->logger->log(100,print_r($orderTemps,true));

        foreach ($messesges as $message) {
            $order  = $this->orderRepositoryInterface->get($message->getOrderId());
            $tel = trim($order->getBillingAddress()->getTelephone(), '+');
            //Validate Contact
            if (isset($orderTemps[$order->getState()]) && $this->api->checkContact($tel)) {

                [$tId, $lang] = explode('.', $orderTemps[$order->getState()]);

                // echo $tel;
                // echo "\n";
                // echo $tId;
                // echo "\n";
                // echo $lang;
                // echo "\n";
                // echo $templates[$lang][$tId];
                // echo "\n";
                

                $response = $this->api->sendWhatsApp(
                    $tel,
                    [
                        $order->getIncrementId(),
                        $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname(),
                        $this->getCurrency($order->getStoreId()) . $order->getBaseGrandTotal(),
                        __('%1 item(s)', (int) $order->getTotalQtyOrdered()),
                    ],
                    $tId, //template
                    $lang, //$this->getLocale($order->getStoreId()),
                    $templates[$lang][$tId] //namespace
                );
                if (isset($response->meta->success)
                    && $response->meta->success == false) {

                    //Update Message Status
                    $message->setStatus(DataHelper::STATUS_ERROR)
                            ->setLog($response->meta->developer_message)
                            ->save();
                } else {

                    //Add Message to Order Comment History
                    $history = $order->addStatusHistoryComment(
                        __("WhatsApp:") . $message->getMessage(),
                        $order->getState()
                    );
                    $history->setIsVisibleOnFront(false);
                    $history->setIsCustomerNotified(true);
                    $history->save();

                    $message->setStatus(DataHelper::STATUS_SENT)->save();
                }
            } else {
                //Update Message Status
                $message->setStatus(DataHelper::STATUS_ERROR)
                    ->setLog(__("Invalid Phone Number"))
                    ->save();
            }
        }
    }
}
