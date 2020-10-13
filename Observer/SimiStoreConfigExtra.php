<?php

namespace Simi\RMAGraphQL\Observer;

use Magento\Framework\Event\ObserverInterface;
use Simi\RMAGraphQL\Helper\Data;

class SimiStoreConfigExtra implements ObserverInterface {

	/**
	 * @var Simi\RMAGraphQl\Helper\Data
	 */
	private $helper;

	public function __construct(
		Data $helper
	) {
		$this->helper            = $helper;
	}

	public function execute( \Magento\Framework\Event\Observer $observer ) {
		$object = $observer->getObject();

		$object->configArray['mirasvit_rma_config'] = $this->helper->getMirasvitStoreConfigs();
	}
}