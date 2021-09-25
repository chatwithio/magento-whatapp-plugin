<?php

namespace Tochat\Whatsapp\Model\Source;

use Magento\Eav\Model\Entity\Attribute\Source\Table;

/**
 * Attribute Source
 */
class Type extends Table
{
    /**
     * Defind Options
     */
    
    const TYPE_ORDER = 1;
    const TYPE_CART = 2;

    /**
     * @param  bool|boolean $withEmpty
     * @param  bool|boolean $defaultValues
     * @return array
     */
    public function getAllOptions($withEmpty = true, $defaultValues = false)
    {
        if (!$this->_options) {
            $this->_options = [
                ['label' => 'Order', 'value' => self::TYPE_ORDER],
                ['label' => 'Abandoned Cart', 'value' => self::TYPE_CART],
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
