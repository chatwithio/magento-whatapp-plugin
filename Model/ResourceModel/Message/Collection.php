<?php

namespace Tochat\Whatsapp\Model\ResourceModel\Message;

use \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * CMS Block Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'id';

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'tochat_whatsapp_message_collection';

    /**
     * Event object
     *
     * @var string
     */
    protected $_eventObject = 'tochat_whatsapp_message_collection';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init(\Tochat\Whatsapp\Model\Message::class, \Tochat\Whatsapp\Model\ResourceModel\Message::class);
    }
}
