<?php
namespace Simi\RMAGraphQL\Model\DataProvider;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Model\ResourceModel\Report\Bestsellers\CollectionFactory as BestSellersCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;



class NewRmaId
{
    protected $orderCollectionFactory;
    
    public function __construct(
        
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory 
    ) {
        
        $this->orderCollectionFactory = $orderCollectionFactory;
    }
    //@param: the array of the id of orders to return
    public function dataArray($customerCurrentId){
        $orderdatas = $this->getOrderCollection($customerCurrentId);
        $orderIdList = [];
        foreach($orderdatas as $orderData){
            array_push($orderIdList, $orderData['increment_id']);
        }
        return $orderIdList;
    }

    private function build_sorter($key) 
    {
        return function ($a, $b) use ($key) {
            return strnatcmp((int)$a[$key], (int)$b[$key]);
        };
    }
    public function getOrderCollection($customerId)
    {
     $collection = $this->orderCollectionFactory->create()->addFieldToFilter('customer_id', $customerId)->getdata(); 
     
     return $collection;
     
    }
}