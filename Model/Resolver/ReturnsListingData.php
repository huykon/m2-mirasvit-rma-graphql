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



class ReturnsListingData implements ResolverInterface 
{

    protected $dataProvider;
    private $orderLoginService;
    private $rmaPolicyConfig;
    protected $_customer;
    protected $_storemanager;
    protected $rmaCollectionFactory;
    protected $frontendConfig;
    
    public function __construct(
        \Simi\RMAGraphQL\Model\DataProvider\RmaDataArray $dataProvider,
        \Mirasvit\Rma\Api\Service\Order\LoginInterface $orderLoginService,
        RmaPolicyConfigInterface $rmaPolicyConfig,
        \Magento\Customer\Model\CustomerFactory $customer,
        \Magento\Store\Model\StoreManagerInterface $storemanager,
        \Mirasvit\Rma\Model\ResourceModel\Rma\CollectionFactory $rmaCollectionFactory,
        \Mirasvit\Rma\Api\Config\FrontendConfigInterface $frontendConfig
    ) {
        $this->dataProvider = $dataProvider;
        $this->orderLoginService = $orderLoginService;
        $this->rmaPolicyConfig   = $rmaPolicyConfig;
        $this->_customer = $customer;
        $this->_storemanager = $storemanager;
        $this->rmaCollectionFactory = $rmaCollectionFactory;
        $this->frontendConfig         = $frontendConfig;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {

        if($context->getUserId()){
            $customerCurrentId = $context->getUserId();

            $collection = $this->rmaCollectionFactory->create();
            $collection->setPageSize($args['pageSize']);
            $collection->setCurPage($args['currentPage']);
            $size = $collection->getSize();
            $maxPages = (int)ceil($size/$args['pageSize']);
            $model = $collection->addFieldToFilter('customer_id', $customerCurrentId)->getItems();
            $dataArray = $this->dataProvider->dataArray($model);
            
            $page_info = [
                'page_size' => $args['pageSize'],
                'current_page' => $args['currentPage'],
                'total_pages' => $maxPages
            ];

            $returnData = [
                'items'=> $dataArray,
                'page_info' => $page_info
            ];
            return $returnData;
        }
        //for offline customer
        elseif(isset($args['increment_id']) && isset($args['email'])){

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
            $websiteID = $this->_storemanager->getStore()->getWebsiteId();
            $customer = $this->_customer->create()->setWebsiteId($websiteID)->loadByEmail($args['email']);
            $customerId = $customer->getId();

            $collection = $this->rmaCollectionFactory->create();
            
            $collection->setPageSize($args['pageSize']);
            $collection->setCurPage($args['currentPage']);
            $size = $collection->getSize();
            $maxPages = (int)ceil($size/$args['pageSize']);

            $model = $collection->addFieldToFilter('customer_id', $customerId)->getItems();
            $dataArrays = $this->dataProvider->dataArray($model);

                //if the config allow to give back all the rma of that customer 
            if ($this->frontendConfig->showGuestRmaByOrder() == 1){
                $page_info = [
                    'page_size' => $args['pageSize'],
                    'current_page' => $args['currentPage'],
                    'total_pages' => $maxPages
                ];

                $returnData = [
                    'items'=> $dataArrays,
                    'page_info' => $page_info
                ];
                return $returnData;
            }
                //if the config only allow to return the rma of this order
            else{
                $returnArray = [];
                foreach($dataArrays as $dataArray){
                    if($dataArray['increment_id'] == $args['increment_id']){
                        array_push($returnArray, $dataArray);
                    }
                }
                $page_info = [
                    'page_size' => $args['pageSize'],
                    'current_page' => $args['currentPage'],
                    'total_pages' => $maxPages
                ];

                $returnData = [
                    'items'=> $returnArray,
                    'page_info' => $page_info
                ];
                return $returnData;
            }
        } elseif (isset($args['increment_id'])) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Wrong Order or Email'));
        }  
    }
}

}
