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



class NewRmaInfo implements ResolverInterface 
{

    protected $dataProvider;
    private $orderLoginService;
    protected $rmaCollectionFactory;
    protected $orderFactory;
    protected $_countryFactory;
    protected $reasonCollection;
    protected $conditionCollection;
    protected $resolutionCollection;
    protected $storeManager;
    
    public function __construct(
        \Simi\RMAGraphQL\Model\DataProvider\RmaDataArray $dataProvider,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Mirasvit\Rma\Api\Service\Order\LoginInterface $orderLoginService,
        \Mirasvit\Rma\Model\ResourceModel\Rma\CollectionFactory $rmaCollectionFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Mirasvit\Rma\Model\ResourceModel\Reason\CollectionFactory $reasonColection,
        \Mirasvit\Rma\Model\ResourceModel\Condition\CollectionFactory $conditionCollection,
        \Mirasvit\Rma\Model\ResourceModel\Resolution\CollectionFactory $resolutionCollection

    ) {
        $this->storeManager = $storeManager;
        $this->dataProvider = $dataProvider;
        $this->orderLoginService = $orderLoginService;
        $this->rmaCollectionFactory = $rmaCollectionFactory;
        $this->orderFactory = $orderFactory;
        $this->_countryFactory = $countryFactory;
        $this->reasonCollection = $reasonColection;
        $this->conditionCollection = $conditionCollection;
        $this->resolutionCollection = $resolutionCollection;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $orderIncrementId = $args['order_increament_Id'];
        $order = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);
        //validate the order whether has entered the right increrment id or not
        if(sizeof($order->getData()) == 0){
            throw new GraphQlInputException( __( 'you enter the wrong id bro' ) );
        } 

        $customerCurrentId = $context->getUserId();
        $orderCollection = $this->orderFactory->create();
        $orderInfo = $orderCollection->loadByIncrementId($orderIncrementId);
          // die(var_dump(get_class_methods($orderInfo)));
        $reasonData = $this->getRequiredDataName($this->reasonCollection->create(), 'is_active', '1');
        $conditionData = $this->getRequiredDataName($this->conditionCollection->create(), 'is_active', '1');
        $resolutionData = $this->getRequiredDataName($this->resolutionCollection->create(), 'is_active', '1');
        $shipping_data = [
            'customer_name'=> $orderInfo->getCustomerName(),
            'company_name'=> $orderInfo->getBillingAddress()->getData()['company'],
            'street_name'=> $orderInfo->getBillingAddress()->getData()['street'],
            'region'=> $orderInfo->getBillingAddress()->getData()['region'],
            'post_code'=>$orderInfo->getBillingAddress()->getData()['postcode'],
            'country_name'=>$this->getCountryName($orderInfo->getBillingAddress()->getData()['country_id']),
            'telephone'=>$orderInfo->getBillingAddress()->getData()['telephone']
        ];
        
        $returnArray = [];
        $itemsOrder = [];
        $returnItems = [];
        $rmaNewItems =[];
        $model = $this->rmaCollectionFactory->create()->getItems();
        $dataArrays = $this->dataProvider->dataArray($model);
        $count = 0;
        $number = [];
        $size = 0;
        foreach($dataArrays as $dataArray){
            // die(var_dump(json_decode(json_encode($dataArray))));
            if($dataArray['increment_id'] == $args['order_increament_Id']){
                array_push($number, $dataArray);
                // die(var_dump($dataArray['order_info']));
                $size = sizeof($dataArray['order_info']);
                foreach($dataArray['order_info'] as $orderItems){
                    array_push($itemsOrder, $orderItems);
                }
                foreach($dataArray['return_item'] as $orderItems){
                    if($orderItems['qty_requested']!= 0){
                        array_push($returnItems, $orderItems);
                        $count++;
                    }
                } 
            }
        }
        $temp = [];
        if($count > 0){
           
            for($i=0; $i<sizeof($itemsOrder); $i++){
                $minus = 0;
                for($j=0; $j<sizeof($returnItems); $j++){
                    if($itemsOrder[$i]['sku'] == $returnItems[$j]['sku']){
                        $quantity_order = (int)$itemsOrder[$i]['qty_ordered'];
                        $quantity_request = (int)$returnItems[$j]['qty_requested'];
                        $minus += $quantity_request;
                        $url = $returnItems[$j]['url'];
                    }
                }
                $itemLeft = $quantity_order - $minus ;
                // die(var_dump($itemsOrder[$i]));
                $RmaNewItem = [
                    'item_id'=>$itemsOrder[$i]['item_id'],
                    'item_name' => $itemsOrder[$i]['name'],
                    'item_url'=> $url,
                    'max_quantity'=> $itemLeft,
                    'reason'=> $reasonData,
                    'condition'=>$conditionData,
                    'resolution'=>$resolutionData
                ];

                array_push($temp, $RmaNewItem); 
            }  
            for ($i=0; $i <$size ; $i++) { 
                array_push($rmaNewItems, $temp[$i]);
            }
        }
        //$count == 0
        else{
            // die(var_dump($order->getAllItems()[0]->getQtyOrdered()));

            foreach($order->getAllItems() as $item){
                $qty = $item->getQtyOrdered();
                $productUrl = $item->getProduct()->getImage();
                $url = $this->getBaseUrl() .'/catalog/product'. $productUrl;
                $productName = $item->getProduct()->getName();
                $sku = $item->getProduct()->getSku();
                $item_id = $item->getProduct()->getId();
                // die(var_dump($item->getData()));
                $data = $item->getData();
                $RmaNewItem = [
                    'item_id'=>$item_id,
                    'item_name' => $productName,
                    'item_url'=> $url,
                    'max_quantity'=> $qty,
                    'reason'=> $reasonData,
                    'condition'=>$conditionData,
                    'resolution'=>$resolutionData
                ];
                array_push($rmaNewItems, $RmaNewItem);

            }
        }

        $finalreturn = [
            'order_id' => $this->getOrderid($args['order_increament_Id']),
            'item' =>$rmaNewItems,
            'order_incrementid' => $orderIncrementId,
            'customer_name' =>$orderInfo->getCustomerName(),
            'customer_email' => $orderInfo->getCustomerEmail(),
            'shiping_adress'=> $shipping_data
        ];

        return $finalreturn;
    }

    private function getCountryName($countryId){
        $countryModel = $this->_countryFactory->create()->loadByCode($countryId);
        return $countryModel->getName();
    }
    private function getRequiredDataName($collection, $fieldToFilter, $valueToFilter){
        $collections = $collection->addFieldToFilter($fieldToFilter, $valueToFilter)->getData();
        $names = [];
        foreach($collections as $data){
            array_push($names, $data['name']);
        }
        return $names;
    }
    private function getBaseUrl(){
        return $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
    }
    private function getOrderid($increment_id){
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $collection = $objectManager->create('Magento\Sales\Model\Order'); 
        $orderInfo = $collection->loadByIncrementId($increment_id);
        $orderId = $orderInfo ->getId();
        return $orderId;  
    }

}
