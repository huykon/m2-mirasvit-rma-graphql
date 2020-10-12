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



class RmaInfoByOrder implements ResolverInterface 
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
        //handling exception
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $collection = $objectManager->create('Magento\Sales\Model\Order'); 
        $orderInfo = $collection->loadByIncrementId($args['increment_id']);
        // die(var_dump($orderInfo->getData()));
        if(sizeof($orderInfo->getData()) == 0){
            throw new GraphQlInputException( __( 'you enter the wrong id bro' ) );
        }  

        //start the logic
        $model = $this->rmaCollectionFactory->create()->getItems();
        $dataArrays = $this->dataProvider->dataArray($model);
        $returnArray =[];
        foreach($dataArrays as $dataArray){
            if($dataArray['increment_id'] == $args['increment_id'] ){
                array_push($returnArray, $dataArray);
            }
        }
        return $returnArray;
    }
}

