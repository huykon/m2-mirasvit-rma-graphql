<?php

/**
 * Connector data helper
 */

namespace Simi\RMAGraphQL\Helper;

class Data extends \Simi\Simiconnector\Helper\Data {
	public function getStoreConfig( $path ) {
		return $this->scopeConfig->getValue( $path );
	}

	public function getMirasvitStoreConfigs() {
		$GeneralConfig  = [
			'return_address'                    => $this->getStoreConfig( 'rma/general/return_address' ),
			'default_status'                    => $this->getStoreConfig( 'rma/general/default_status' ),
			'default_user'                      => $this->getStoreConfig( 'rma/general/default_user' ),
			'is_require_shipping_confirmation'  => $this->getStoreConfig( 'rma/general/is_require_shipping_confirmation' ),
			'shipping_confirmation_text'        => $this->getStoreConfig( 'rma/general/shipping_confirmation_text' ),
			'is_offline_orders'                 => $this->getStoreConfig( 'rma/general/is_offline_orders' ),
			'is_gift_active'                    => $this->getStoreConfig( 'rma/general/is_gift_active' ),
			'is_helpdesk_active'                => $this->getStoreConfig( 'rma/general/is_helpdesk_active' ),
			'brand_attribute'                   => $this->getStoreConfig( 'rma/general/brand_attribute' ),
			'file_allowed_extensions'           => $this->getStoreConfig( 'rma/general/file_allowed_extensions' ),
			'shipping_label_allowed_extensions' => $this->getStoreConfig( 'rma/general/shipping_label_allowed_extensions' ),
			'file_size_limit'                   => $this->getStoreConfig( 'rma/general/file_size_limit' )
		];
		$CustomerConfig = [
			'is_active'                => $this->getStoreConfig( 'rma/frontend/is_active' ),
			'show_guest_rma_by_order'  => $this->getStoreConfig( 'rma/frontend/show_guest_rma_by_order' ),
			'rma_customer_requirement' => $this->getStoreConfig( 'rma/frontend/rma_customer_requirement' )
		];

		$PolicyConfig       = [
			'return_period'           => $this->getStoreConfig( 'rma/policy/is_active' ),
			'allow_in_statuses'       => $this->getStoreConfig( 'rma/policy/is_active' ),
			'is_allow_multiple_order' => $this->getStoreConfig( 'rma/policy/is_active' ),
			'return_only_shipped'     => $this->getStoreConfig( 'rma/policy/is_active' ),
			'is_active'               => $this->getStoreConfig( 'rma/policy/is_active' ),
			'policy_block'            => $this->getStoreConfig( 'rma/policy/is_active' )
		];
		$NumberConfig       = [
			'allow_manual'   => $this->getStoreConfig( 'rma/number/is_active' ),
			'format'         => $this->getStoreConfig( 'rma/number/is_active' ),
			'reset_counter'  => $this->getStoreConfig( 'rma/number/is_active' ),
			'counter_start'  => $this->getStoreConfig( 'rma/number/is_active' ),
			'counter_step'   => $this->getStoreConfig( 'rma/number/is_active' ),
			'counter_length' => $this->getStoreConfig( 'rma/number/is_active' )
		];
		$NotificationConfig = [
			'sender_email'            => $this->getStoreConfig( 'rma/notification/is_active' ),
			'customer_email_template' => $this->getStoreConfig( 'rma/notification/is_active' ),
			'admin_email_template'    => $this->getStoreConfig( 'rma/notification/is_active' ),
			'rule_template'           => $this->getStoreConfig( 'rma/notification/is_active' ),
			'send_email_bcc_type'     => $this->getStoreConfig( 'rma/notification/is_active' ),
			'send_email_bcc'          => $this->getStoreConfig( 'rma/notification/is_active' )
		];
		$AdvanceConfig      = [
			'apply_styles' => $this->getStoreConfig( 'rma/advanced_settings/apply_styles' )
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
}