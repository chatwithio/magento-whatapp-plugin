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
    private $templates = [];

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

    public function getCurrency($code)
    {
        if (!isset($this->currency[$code])) {
            $currency = $this->currencyFactory->create()->load($code); 
            $this->currency[$code] = $currency->getCurrencySymbol();
        }
        return $this->currency[$code];
    }

    public function getAssignedTemplates($code)
    {
        if (!isset($this->templates[$code])) {
            $this->templates[$code] = $this->dataHelper->getTemplatesId($code);
        }
        return $this->templates[$code];
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
                    $templates[$template->language][$template->name] = $template;
                }
            }
        }

        //$orderTemps = $this->dataHelper->getTemplatesId();

        // print_r($orderTemps);

        //$this->logger->log(100,print_r($orderTemps,true));

        foreach ($messesges as $message) {
            $order  = $this->orderRepositoryInterface->get($message->getOrderId());

            $orderTemps = $this->getAssignedTemplates($order->getStore()->getStoreCode());

            $tel = trim($order->getBillingAddress()->getTelephone(), '+');
            //Validate Contact
            if (isset($orderTemps[$order->getState()]) && $this->api->checkContact($tel)) {

                [$tId, $lang] = explode('.', $orderTemps[$order->getState()]);

                //Search the template body content for placeholder and fill them with values
                $tempObj = $templates[$lang][$tId];
                $body = current(array_filter($tempObj->components, function($e){
                    return $e->type == 'BODY';
                }));
                $name = null;
                foreach ($order->getAllVisibleItems() as $item) {
                    $name[] = $item->getName();
                }
                $itemName = implode(',', $name);
                preg_match_all("/{{+\d+}}/",$body->text, $placeholders);
                $placeholders = $placeholders[0];
                $values = [
                    1 => $order->getIncrementId(),
                    2 => $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname(),
                    3 => strlen($itemName) > 150 ? substr($itemName, 0, 150) . '...' : $itemName,
                    4 => $this->getCurrency($order->getStoreCurrencyCode()) . number_format($order->getBaseGrandTotal(),2),
                ];
                if(count($placeholders)){
                    foreach(range(1, 4) as $i){
                        if(array_search("{{".$i."}}",$placeholders) === FALSE){
                            unset($values[$i]);
                        }
                    }
                }
                $messageStr = str_replace(array_map(function($e){
                    return '{{' . $e . '}}';
                }, array_keys($values)), $values, $body->text);
                //END
                

                $response = $this->api->sendWhatsApp(
                    $tel,
                    $values,
                    $tId, //template
                    $lang,
                    $tempObj->namespace //namespace
                );
                if (isset($response->meta->success)
                    && $response->meta->success == false) {

                    //Update Message Status
                    $message->setStatus(DataHelper::STATUS_ERROR)
                            ->setMessage($messageStr)
                            ->setSentOn(date('Y-m-d H:i:s'))
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

                    $message->setStatus(DataHelper::STATUS_SENT)
                    ->setSentOn(date('Y-m-d H:i:s'))
                    ->setMessage($messageStr)
                    ->save();
                }
            } else {
                //Update Message Status
                $message->setStatus(DataHelper::STATUS_ERROR)
                    ->setSentOn(date('Y-m-d H:i:s'))
                    ->setLog(__("Invalid Phone Number"))
                    ->save();
            }
        }
    }
}
