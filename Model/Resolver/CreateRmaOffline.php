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



class CreateRma implements ResolverInterface 
{

    protected $dataProvider;
    protected $_storemanager;
    protected $dataProcessor;
    protected $rmaSaveService;
    protected $customerStrategy;
    public $guestStrategy;
    protected $noAccessStrategy;
    protected $orderAbstractFactory;
    protected $itemListBuilder;
    protected $rmaCollectionFactory;
    protected $saveManagement;
    
    public function __construct(
        
    ) {
        $this->dataProvider = $dataProvider;
        $this->_storemanager = $storemanager;
        $this->dataProcessor = $dataProcessor;
        $this->rmaSaveService = $rmaSaveService;
        $this->customerStrategy = $customerStrategy;
        $this->guestStrategy    = $guestStrategy;
        $this->noAccessStrategy = $noAccessStrategy;
        $this->orderAbstractFactory   = $orderAbstractFactory;
        $this->itemListBuilder        = $itemListBuilder;
        $this->rmaCollectionFactory = $rmaCollectionFactory;
        $this->rmaFactory = $rmaFactory;
        $this->saveManagement = $saveManagement;
        
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
       die(var_dump($args));
    }
}


