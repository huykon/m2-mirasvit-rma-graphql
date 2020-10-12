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



class DetailRmaInfo implements ResolverInterface 
{

    protected $dataProvider;
    private $orderLoginService;
    private $rmaPolicyConfig;
    protected $_customer;
    protected $_storemanager;
    protected $rmaCollectionFactory;
    
    public function __construct(
        \Simi\RMAGraphQL\Model\DataProvider\RmaDataArray $dataProvider,
        \Mirasvit\Rma\Api\Service\Order\LoginInterface $orderLoginService,
        RmaPolicyConfigInterface $rmaPolicyConfig,
        \Magento\Customer\Model\CustomerFactory $customer,
        \Magento\Store\Model\StoreManagerInterface $storemanager,
        \Mirasvit\Rma\Model\ResourceModel\Rma\CollectionFactory $rmaCollectionFactory
    ) {
        $this->dataProvider = $dataProvider;
        $this->orderLoginService = $orderLoginService;
        $this->rmaPolicyConfig   = $rmaPolicyConfig;
        $this->_customer = $customer;
        $this->_storemanager = $storemanager;
        $this->rmaCollectionFactory = $rmaCollectionFactory;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $model = $this->rmaCollectionFactory->create()->getItems();
        $dataArrays = $this->dataProvider->dataArray($model);
        $count = 0;
        foreach($dataArrays as $dataArray){
            if($args['guest_id'] == $dataArray['guest_id']){
                $count++;

                return $dataArray;
            }
        }

        //exception 
        if($count == 0){
            throw new GraphQlInputException( __( 'you enter the wrong guest ID bro' ) );
        } 
    }
}

