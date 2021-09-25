<?php
namespace Tochat\Whatsapp\Ui\Component\Listing\Log\Column;

use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Payment\Helper\Data;
use Tochat\Whatsapp\Model\ResourceModel\Message\CollectionFactory;
use Tochat\Whatsapp\Helper\Data as DataHelper;

/**
 * Class Extradata
 */
class Extradata extends Column
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
                if(!empty($item['extradata'])){
                    $arr = $this->dataHelper->unserialize($item['extradata']);
                    $item[$this->getData('name')] =  implode("<br/>", array_map(function($v, $k){
                        return ucfirst($k) . ':' . $v;
                    },$arr, array_keys($arr)));    
                }
            }
        }
        return $dataSource;
    }
}
