<?php

declare (strict_types = 1);

namespace Tochat\Whatsapp\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Wippet\Customer\Model\Customer\Attribute\Source\Workarea;
/**
 * Create AddCustomerMobileAttribute Attributes
 */
class AddCustomerMobileAttribute implements DataPatchInterface {

	/**
	 * @var CustomerSetupFactory
	 */
	private $customerSetupFactory;

	/**
	 * @var ModuleDataSetupInterface
	 */
	private $moduleDataSetup;

	/**
	 * @param CustomerSetupFactory     $customerSetupFactory
	 * @param ModuleDataSetupInterface $moduleDataSetup
	 */
	public function __construct(
		CustomerSetupFactory $customerSetupFactory,
		ModuleDataSetupInterface $moduleDataSetup
	) {
		$this->customerSetupFactory = $customerSetupFactory;
		$this->moduleDataSetup = $moduleDataSetup;
	}

	public function apply() {

		$customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
	
		$customerSetup->addAttribute(
			Customer::ENTITY,
			'mobile',
			[
				'type' => 'varchar',
				'label' => 'Mobile',
				'input' => 'text',
				'required' => false,
				'sort_order' => 100,
				'position' => 100,
				'visible' => true,
				'system' => false,
				'is_used_in_grid' => false,
			]
		);
		
		$mobileAttribute = $customerSetup->getEavConfig()->getAttribute('customer', 'mobile');
		$mobileAttribute->setData(
			'used_in_forms',
			[
				'adminhtml_customer',
				'customer_account_create',
				'customer_account_edit',
			]
		);
		$mobileAttribute->save();

		return $this;
	}

	/**
	 * @return array
	 */
	public static function getDependencies(): array
	{
		return [];
	}

	/**
	 * @return string
	 */
	public static function getVersion(): string {
		return '1.0.0';
	}

	/**
	 * @return array
	 */
	public function getAliases(): array
	{
		return [];
	}
}
