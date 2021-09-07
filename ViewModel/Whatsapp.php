<?php
declare(strict_types=1);

namespace Tochat\Whatsapp\ViewModel;

use Tochat\Whatsapp\Helper\Data;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Whatsapp view model.
 */
class Whatsapp extends DataObject implements ArgumentInterface
{
    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * @param Data $dataHelper
     */
    public function __construct(
        Data $dataHelper
    ) {
        parent::__construct();
        $this->dataHelper = $dataHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessages()
    {
        return $this->dataHelper->getMessages();
    }

    public function isActive() : ?bool
    {
        return $this->dataHelper->isActive();
    }

    public function timeElapsedString($datetime, $full = false)
    {
        return $this->dataHelper->timeElapsedString($datetime);
    }

    public function getRecentMessage($orderID)
    {
        return $this->dataHelper->getRecentMessage($orderID);
    }
}
