<?php
namespace Simi\RMAGraphQL\Model\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Model\ResourceModel\Report\Bestsellers\CollectionFactory as BestSellersCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Mirasvit\Rma\Api\Config\RmaPolicyConfigInterface;



class AllowOrderToCreate implements ResolverInterface 
{

    protected $dataProvider;
    private $orderLoginService;
    private $rmaPolicyConfig;
    protected $_customer;
    protected $_storemanager;
    
    public function __construct(
        \Simi\RMAGraphQL\Model\DataProvider\NewRmaId $dataProvider,
        \Mirasvit\Rma\Api\Service\Order\LoginInterface $orderLoginService,
        RmaPolicyConfigInterface $rmaPolicyConfig,
        \Magento\Customer\Model\CustomerFactory $customer,
        \Magento\Store\Model\StoreManagerInterface $storemanager
    ) {
        $this->dataProvider = $dataProvider;
        $this->orderLoginService = $orderLoginService;
        $this->rmaPolicyConfig   = $rmaPolicyConfig;
        $this->_customer = $customer;
        $this->_storemanager = $storemanager;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $dataArray = [];
        if($context->getUserId()){
            $customerCurrentId = $context->getUserId();
            $dataArray = $this->dataProvider->dataArray($customerCurrentId);
        }
        elseif($args['increment_id'] && $args['email']){
            try {
                $order = $this->orderLoginService->getOrder(
                    $args['increment_id'],
                    $args['email']
                );
                if ($order) {
                    if (!$order->getIsOffline() &&
                        !in_array($order->getStatus(), $this->rmaPolicyConfig->getAllowRmaInOrderStatuses())
                    ) {
                        throw new \Magento\Framework\Exception\LocalizedException(
                            __('You have no completed orders to request RMA or your orders were placed more than %1 days ago. Please, contact customer service.',
                                $this->rmaPolicyConfig->getReturnPeriod()
                            )
                        );
                }
                array_push($dataArray, $args['increment_id']);
            } elseif ($args['increment_id']) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Wrong Order #, Email'));
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Wrong Order or Email'));
        }
    }
    return $dataArray;
}

}
