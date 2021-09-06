<?php

namespace Tochat\Whatsapp\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Message model
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Message extends AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('tochat_whatsapp_message', 'id');
    }
}
