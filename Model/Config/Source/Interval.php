<?php

namespace Tochat\Whatsapp\Model\Config\Source;

use Tochat\Whatsapp\Helper\Api;

class Interval implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '1', 'label' => __("%1 hour",1)],
            ['value' => '3', 'label' => __("%1 hours",3)],
            ['value' => '5', 'label' => __("%1 hours",5)],
            ['value' => '7', 'label' => __("%1 hours",7)],
            ['value' => '10', 'label' => __("%1 hours",10)],
            ['value' => '24', 'label' => __("%1 Day",1)],
            ['value' => '48', 'label' => __("%1 Days",2)],
            ['value' => '72', 'label' => __("%1 Days",3)],
            ['value' => '120', 'label' => __("%1 Days",5)],
            ['value' => '168', 'label' => __("%1 Days",7)],
        ];   
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        $opts = $this->toOptionArray();
        return array_combine(array_column($opts, 'value'), array_column($opts, 'label'));
    }
}
