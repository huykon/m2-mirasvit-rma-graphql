<?php

namespace Simi\RMAGraphQL\Model\Resolver;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;


class ConfigData implements ResolverInterface {
	private $scopeConfig;

	public function __construct(
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
	) {
		$this->scopeConfig = $scopeConfig;
	}

	public function resolve( Field $field, $context, ResolveInfo $info, array $value = null, array $args = null ) {
		$GeneralConfig  = [
			'return_address'                    => $this->getDataType( 'rma/general/return_address' ),
			'default_status'                    => $this->getDataType( 'rma/general/default_status' ),
			'default_user'                      => $this->getDataType( 'rma/general/default_user' ),
			'is_require_shipping_confirmation'  => $this->getDataType( 'rma/general/is_require_shipping_confirmation' ),
			'shipping_confirmation_text'        => $this->getDataType( 'rma/general/shipping_confirmation_text' ),
			'is_offline_orders'                 => $this->getDataType( 'rma/general/is_offline_orders' ),
			'is_gift_active'                    => $this->getDataType( 'rma/general/is_gift_active' ),
			'is_helpdesk_active'                => $this->getDataType( 'rma/general/is_helpdesk_active' ),
			'brand_attribute'                   => $this->getDataType( 'rma/general/brand_attribute' ),
			'file_allowed_extensions'           => $this->getDataType( 'rma/general/file_allowed_extensions' ),
			'shipping_label_allowed_extensions' => $this->getDataType( 'rma/general/shipping_label_allowed_extensions' ),
			'file_size_limit'                   => $this->getDataType( 'rma/general/file_size_limit' )
		];
		$CustomerConfig = [
			'is_active'                => $this->getDataType( 'rma/frontend/is_active' ),
			'show_guest_rma_by_order'  => $this->getDataType( 'rma/frontend/show_guest_rma_by_order' ),
			'rma_customer_requirement' => $this->getDataType( 'rma/frontend/rma_customer_requirement' )
		];

		$PolicyConfig       = [
			'return_period'           => $this->getDataType( 'rma/policy/is_active' ),
			'allow_in_statuses'       => $this->getDataType( 'rma/policy/is_active' ),
			'is_allow_multiple_order' => $this->getDataType( 'rma/policy/is_active' ),
			'return_only_shipped'     => $this->getDataType( 'rma/policy/is_active' ),
			'is_active'               => $this->getDataType( 'rma/policy/is_active' ),
			'policy_block'            => $this->getDataType( 'rma/policy/is_active' )
		];
		$NumberConfig       = [
			'allow_manual'   => $this->getDataType( 'rma/number/is_active' ),
			'format'         => $this->getDataType( 'rma/number/is_active' ),
			'reset_counter'  => $this->getDataType( 'rma/number/is_active' ),
			'counter_start'  => $this->getDataType( 'rma/number/is_active' ),
			'counter_step'   => $this->getDataType( 'rma/number/is_active' ),
			'counter_length' => $this->getDataType( 'rma/number/is_active' )
		];
		$NotificationConfig = [
			'sender_email'            => $this->getDataType( 'rma/notification/is_active' ),
			'customer_email_template' => $this->getDataType( 'rma/notification/is_active' ),
			'admin_email_template'    => $this->getDataType( 'rma/notification/is_active' ),
			'rule_template'           => $this->getDataType( 'rma/notification/is_active' ),
			'send_email_bcc_type'     => $this->getDataType( 'rma/notification/is_active' ),
			'send_email_bcc'          => $this->getDataType( 'rma/notification/is_active' )
		];
		$AdvanceConfig      = [
			'apply_styles' => $this->getDataType( 'rma/advanced_settings/apply_styles' )
		];

		return $ConfigData = [
			'general'     => $GeneralConfig,
			'customer'    => $CustomerConfig,
			'policy'      => $PolicyConfig,
			'number'      => $NumberConfig,
			'noti_config' => $NotificationConfig,
			'advance'     => $AdvanceConfig
		];

	}

	private function getDataType( $string ) {
		return $this->scopeConfig->getValue( $string, \Magento\Store\Model\ScopeInterface::SCOPE_STORE );
	}

}
