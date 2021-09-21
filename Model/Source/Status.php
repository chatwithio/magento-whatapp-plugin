<?php

namespace Tochat\Whatsapp\Model\Source;

use Magento\Eav\Model\Entity\Attribute\Source\Table;

/**
 * Attribute Source
 */
class Status extends Table
{
    /**
     * Defind Options
     */
    
    const STATUS_SENT = 1;
    const STATUS_PENDING = 2;
    const STATUS_FAILED = 3;

    /**
     * @param  bool|boolean $withEmpty
     * @param  bool|boolean $defaultValues
     * @return array
     */
    public function getAllOptions($withEmpty = true, $defaultValues = false)
    {
        if (!$this->_options) {
            $this->_options = [
                ['label' => 'Sent', 'value' => self::STATUS_SENT],
                ['label' => 'Pending', 'value' => self::STATUS_PENDING],
                ['label' => 'Failed', 'value' => self::STATUS_FAILED],
            ];
        }

        return $this->_options;
    }

    /**
     * @param  int    $value
     * @return string|false
     */
    public function getOptionText($value)
    {
        if (!$this->_options) {
            $this->_options = $this->getAllOptions();
        }
        foreach ($this->_options as $option) {
            if ($option['value'] == $value) {
                return $option['label'];
            }
        }
        return false;
    }
}
