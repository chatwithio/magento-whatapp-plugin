<?php

namespace Tochat\Whatsapp\Model\Config\Source;

use Tochat\Whatsapp\Helper\Api;

class Template implements \Magento\Framework\Option\ArrayInterface
{
    private $templates = [];

    public function __construct(
        Api $api
    ) {
        $response = $api->getTemplates();
        if (isset($response->waba_templates)) {
            foreach($response->waba_templates as $template){
                if($template->status == 'approved'){
                    $this->templates[] = [
                        'value' => $template->name . '.' . $template->language, 
                        'label' => $template->name . '(' . $template->language . ')'
                    ];    
                }
            }
        }
        usort($this->templates, function($a, $b){
            return $a['label'] <=> $b['label'];
        });
    }

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return $this->templates;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return array_combine(array_column($this->templates, 'value'), array_column($this->templates, 'label'));
    }
}
