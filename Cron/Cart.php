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
use Magento\Framework\Exception\NoSuchEntityException;

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

    public function getCustomerData($quote){
        
        if(!empty($quote->getCustomerId())){
            try{
                $customer = $this->customerRepository->getById($quote->getCustomerId());
                return [
                    'name' => $customer->getFirstname() . ' ' . $customer->getLastname(),
                    'email' => $customer->getEmail(),
                    'tel' => $customer->getExtensionAttributes()->getMobile() ?? $quote->getBillingAddress()->getTelephone(),
                ];
            }catch(NoSuchEntityException  $e){}
        }

        $email = $quote->getCustomerEmail() ?? $quote->getBillingAddress()->getEmail();

        if($email){
            try{
                $customer = $this->customerRepository->get($email);
                return [
                    'name' => $customer->getFirstname() . ' ' . $customer->getLastname(),
                    'email' => $customer->getEmail(),
                    'tel' => $customer->getExtensionAttributes()->getMobile() ?? $quote->getBillingAddress()->getTelephone(),
                ];
            }catch(NoSuchEntityException  $e){
                if(!empty($quote->getBillingAddress()->getTelephone())){
                    $billingAddress = $quote->getBillingAddress();
                    return [
                        'name' => $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname(),
                        'email' => $billingAddress->getEmail(),
                        'tel' => $billingAddress->getTelephone(),
                    ];
                }
            }
        }

        return false;
    }

    public function validate($data){
        if(count(array_filter($data)) == 3){
            return true;            
        }
        return false;
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

        $collections = $this->collectionFactory->create()
            ->addFieldToFilter('is_active',1)
            ->addFieldToFilter(
                new \Zend_Db_Expr("TIMESTAMPDIFF(MINUTE, `updated_at`, now())"),
                intval($interval * 60)
            );

        foreach($collections as $quote){

            $customerData = $this->getCustomerData($quote);

            if(!$this->validate($customerData)) continue;

            $store = $quote->getStore();
            $tel = trim($customerData['tel'], '+');
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
                    1 => $customerData['name'],
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
                        'email' => $customerData['email'],
                        'mobile' => $tel
                    ]))
                    ->setSentOn(date('Y-m-d H:i:s'))
                    ->setLog($log)
                    ->save();
            }
        }
    }
}
