<?php

namespace Tochat\Whatsapp\Plugin\Customer\Model;

use Magento\Customer\Api\Data\CustomerExtensionFactory;
use Magento\Customer\Model\Customer as CustomerModel;

class Customer {
	/**
	 * @var CustomerExtensionFactory
	 */
	private $extensionFactory;

	/**
	 * @param CustomerExtensionFactory        $extensionFactory
	 */
	public function __construct(
		CustomerExtensionFactory $extensionFactory
	) {
		$this->extensionFactory = $extensionFactory;
	}

	/**
	 * @param  Customer $subject
	 * @param  Customer $entity
	 * @return Customer
	 */
	public function afterAfterLoad(
		CustomerModel $subject,
		CustomerModel $entity
	): CustomerModel{
		$extensionAttributes = $entity->getExtensionAttributes();
		if (!$extensionAttributes) {
			$extensionAttributes = $this->extensionFactory->create();
		}
		$extensionAttributes->setMobile($subject->getMobile());
		$entity->setExtensionAttributes($extensionAttributes);
		return $entity;
	}
}
