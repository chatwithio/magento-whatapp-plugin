<?php
namespace Tochat\Whatsapp\Ui\Component\Listing\Sales\Order\Column;

use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Payment\Helper\Data;
use Tochat\Whatsapp\Model\ResourceModel\Message\CollectionFactory;
use Tochat\Whatsapp\Helper\Data as DataHelper;

/**
 * Class PaymentMethod
 */
class Whatsapp extends Column
{
    /**
     * @var DataHelper
     */
    protected $dataHelper;

    /**
     * @var string[]
     */
    protected $phones = [];

    /**
     * Constructor
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param DataHelper $dataHelper
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        DataHelper $dataHelper,
        array $components = [],
        array $data = []
    ) {
        $this->dataHelper = $dataHelper;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function getPhoneNumber($orderId)
    {
        if (!isset($this->phones[$orderId])) {
            $this->phones[$orderId] = $this->dataHelper->getOrderTelephoneById($orderId);
        }
        return $this->phones[$orderId];
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items']) && $this->dataHelper->isActive()) {
            foreach ($dataSource['data']['items'] as & $item) {

                $message = $this->dataHelper->getRecentMessage($item['entity_id']);

                $phone = $this->getPhoneNumber($item['entity_id']);

                $htm = '<div class="whatsapp-block">';

                $htm .= "<button class='whatsapp' 
                            data-phone='{$phone}'
                            data-status='{$item['status']}'
                            data-id='{$item['entity_id']}'>" .__("Click to Chat"). "</button>";

                $htm .= '<br/>';

                if ($message) {
                    $htm .= __("Last message sent %1:", $this->dataHelper->timeElapsedString($message->getCreatedAt()));

                    $htm .= '<br/>';

                    $htm .= '<b>'.__("WhatsApp").':</b>';

                    $htm .= $message->getMessage();
                }

                $htm .= '</div>';

                $item[$this->getData('name')] =  $htm;
            }
        }

        return $dataSource;
    }
}
