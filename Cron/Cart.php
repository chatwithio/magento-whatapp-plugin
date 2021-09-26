<?php

namespace Tochat\Whatsapp\Cron;

use \Psr\Log\LoggerInterface;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory;
use Tochat\Whatsapp\Helper\Api;
use Tochat\Whatsapp\Helper\Data as DataHelper;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Tochat\Whatsapp\Model\MessageFactory;

class Cart
{
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

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var MessageFactory
     */
    protected $messageFactory;


    public function __construct(
        LoggerInterface $logger,
        CollectionFactory $collectionFactory,
        Api $api,
        DataHelper $dataHelper,
        StoreManagerInterface $storeManagerInterface,
        CustomerRepositoryInterface $customerRepository,
        MessageFactory $messageFactory,
        CurrencyFactory $currencyFactory
    ) {
        $this->api = $api;
        $this->dataHelper = $dataHelper;
        $this->logger = $logger;
        $this->collectionFactory = $collectionFactory;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->currencyFactory = $currencyFactory;
        $this->customerRepository = $customerRepository;
        $this->messageFactory = $messageFactory;
    }

    public function getCurrency($code)
    {
        if (!isset($this->currency[$code])) {
            $currency = $this->currencyFactory->create()->load($code); 
            $this->currency[$code] = $currency->getCurrencySymbol();
        }
        return $this->currency[$code];
    }

    public function execute()
    {
        if (!$this->api->isActive()
            || !$this->dataHelper->getModuleConfig('automation/abandoned/status')
        ) {
            return;
        }

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

        $interval = (float)$this->dataHelper->getModuleConfig('automation/abandoned/interval');

        $interval = $interval < 1 ? intval($interval * 60) . ' MINUTE' : $interval . ' HOUR' ;

        $collections = $this->collectionFactory->create()
            ->addFieldToFilter('customer_id',['neq' => 'NULL'])
            ->addFieldToFilter('is_active',1)
            ->addFieldToFilter(
                new \Zend_Db_Expr("DATE_FORMAT(`updated_at`, '%Y-%m-%d %H:%i') = DATE_FORMAT((now() - INTERVAL $interval), '%Y-%m-%d %H:%i')"),
                1
            );

        foreach($collections as $quote){

            $store = $quote->getStore();
            $customer = $this->customerRepository->getById($quote->getCustomerId());
            $tel = trim($customer->getExtensionAttributes()->getMobile(), '+');
            $template = $this->dataHelper->getModuleConfig('automation/abandoned/template', $store->getStoreCode());

            if(!empty($tel) 
                && !empty($template)
                && $quote->hasItems()
                && $this->api->checkContact($tel)){

                [$tId, $lang] = explode('.', $template);


                //Search the template body content for placeholder and fill them with values
                $tempObj = $templates[$lang][$tId];
                $body = current(array_filter($tempObj->components, function($e){
                    return $e->type == 'BODY';
                }));
                $name = null;
                foreach ($quote->getAllVisibleItems() as $item) {
                    $name[] = $item->getName();
                }
                $itemName = implode(',', $name);
                preg_match_all("/{{+\d+}}/",$body->text, $placeholders);
                $placeholders = $placeholders[0];
                $values = [
                    1 => $customer->getFirstname() . ' ' . $customer->getLastname(),
                    2 => strlen($itemName) > 150 ? substr($itemName, 0, 150) . '...' : $itemName,
                    3 => $this->getCurrency($quote->getStoreCurrencyCode()) . number_format($quote->getBaseGrandTotal(),2),
                    4 => $store->getName(),
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

                //Send Message via API
                $response = $this->api->sendWhatsApp(
                    $tel,
                    $values,
                    $tId, //template
                    $lang,
                    $tempObj->namespace //namespace
                );

                $status = DataHelper::STATUS_SENT;
                $log = null;

                if (
                    (
                        isset($response->meta->success) 
                        && 
                        $response->meta->success == false
                    )
                    || isset($response->errors)
                ) {
                    $status = DataHelper::STATUS_ERROR;
                    $log = implode("|",array_map(function($ele){
                            return $ele->details;
                        }, $response->errors));
                }

                $model = $this->messageFactory->create();
                    
                $model->setStatus($status)
                    ->setMessage($messageStr)
                    ->setType(DataHelper::TYPE_CART)
                    ->setExtradata($this->dataHelper->serialize([
                        'email' => $customer->getEmail(),
                        'mobile' => $tel
                    ]))
                    ->setSentOn(date('Y-m-d H:i:s'))
                    ->setLog($log)
                    ->save();
            }
        }
    }
}
