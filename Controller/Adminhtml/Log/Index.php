<?php

namespace Tochat\Whatsapp\Controller\Adminhtml\Log;

class Index extends \Magento\Backend\App\Action
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        /**
         * Set active menu item
         */
        $resultPage->setActiveMenu('Tochat_Whatsapp::log');
        $resultPage->getConfig()->getTitle()->prepend(__('Tochat Log'));

        /**
         * Add breadcrumb item
         */
        $resultPage->addBreadcrumb(__('Tochat Log'), __('Tochat Log'));
        $resultPage->addBreadcrumb(__('Tochat Log'), __('Tochat Log'));

        return $resultPage;
    }
}
