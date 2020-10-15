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
        \Simi\RMAGraphQL\Model\DataProvider\RmaDataArray $dataProvider,
        \Magento\Store\Model\StoreManagerInterface $storemanager,
        \Mirasvit\Rma\Controller\Rma\PostDataProcessor $dataProcessor,
        \Mirasvit\Rma\Api\Service\Rma\RmaManagement\SaveInterface $rmaSaveService,
        \Mirasvit\Rma\Helper\Controller\Rma\CustomerStrategy $customerStrategy,
        GuestStrategy $guestStrategy,
        \Mirasvit\Rma\Helper\Controller\Rma\NoAccessStrategy $noAccessStrategy,
        \Mirasvit\Rma\Service\Order\OrderAbstractFactory $orderAbstractFactory,
        \Mirasvit\Rma\Service\Item\ItemListBuilder $itemListBuilder,
        \Mirasvit\Rma\Model\ResourceModel\Rma\CollectionFactory $rmaCollectionFactory,
        \Mirasvit\Rma\Model\RmaFactory $rmaFactory,
        \Mirasvit\Rma\Service\Rma\RmaManagement\Save $saveManagement
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
        // die(var_dump(get_class_methods($this->guestStrategy)));

        $orderId = $args['newRmaInfo']['order_ids'][0];
        $orderItemIds = $this->getOrderItemsId($args['newRmaInfo']);
        
        for ($i=0; $i <sizeof($args['newRmaInfo']['items']) ; $i++) { 
            $args['newRmaInfo']['items'][$orderItemIds[$i]] = $args['newRmaInfo']['items'][$i];
            unset($args['newRmaInfo']['items'][$i]);
        }

        $strategy = $this->getStrategy($context, $orderId);
        $data = $args['newRmaInfo'];
        $data['store_id'] = (int)$context->getExtensionAttributes()->getStore()->getId();
            // $data = $this->dataProcessor->createOfflineOrder($data);
        $this->getPerfomer($context, $orderId);
        
        $rma  = $this->rmaSaveService->saveRma(
            $this->getPerfomer($context, $orderId),
            $this->dataProcessor->filterRmaData($data),
            $this->dataProcessor->filterRmaItems($data)
        );
        
        
        $model = $this->rmaCollectionFactory->create()->getItems();
        $dataArrays = $this->dataProvider->dataArray($model);
        $count = 0;
        foreach($dataArrays as $dataArray){
            if($dataArray['rma_id'] == $rma['rma_id']){
                $count++;
                return $dataArray;
            }
        }  
        if($count == 0){
            throw new \Magento\Framework\Exception\LocalizedException(__('Something went wrong'));
        }
    }
    private function getPerfomer($context, $orderId){
        try {
            if ($context->getUserId()) {
                return $this->customerStrategy->getPerformer();
            } else {
                $this->guestStrategy->setOrderId($orderId);
                return $this->guestStrategy->getPerformer();

            }
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return $this->noAccessStrategy->getPerformer();
        }
    }
    private function getStrategy($context, $orderId){
        if ($context->getUserId()) {
            return $this->customerStrategy;
        } else {
            $this->guestStrategy->setOrderId($orderId);
            return $this->guestStrategy;

        }
    }
    private function getOrderItemsId($data){
        if (isset($data['order_ids'])) {
            foreach ((array)$data['order_ids'] as $orderId) {
                $params['order_id']        = $orderId;
                $order                     = $this->orderAbstractFactory->get($params);
                $itemCollections[$orderId] = $this->itemListBuilder->getList($order);
            }
        }
        // die(var_dump(json_decode(json_encode($itemCollections[$orderId]))));
        $returnArray = [];
        foreach($itemCollections[$orderId] as $k => $item){
            array_push($returnArray, $k);
        }
        return $returnArray;
    }

    public function offlineSaveRma($performer, $data, $items)
    {
        // die(var_dump(json_decode(json_encode($performer))));

        $rma = $this->rmaFactory->create();
        if (isset($data['rma_id']) && $data['rma_id']) {
            $rma->load($data['rma_id']);
        }
        unset($data['rma_id']);
    }
}