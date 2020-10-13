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
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;



class RmaDataArray
{
    protected $storeManager;
    protected $customerSession;
    protected $rmaCollectionFactory;
    protected $productCollection;
    protected $orderRepository;
    protected $rmaManagement;
    protected $itemModelFactory;
    protected $_productCollectionFactory;
    protected $messageFactory;
    protected $_productRepository;
    protected $attachmentFactory; 
    private $fileSystem;
    protected $attachmentRepository;
    
    public function __construct(
        CollectionFactory $productCollection,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Category $categoryModel,
        \Mirasvit\Rma\Model\ResourceModel\Rma\CollectionFactory $rmaCollectionFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Mirasvit\Rma\Api\Service\Rma\RmaManagementInterface $rmaManagement,
        \Mirasvit\Rma\Model\ResourceModel\Item\CollectionFactory $itemModelFactory,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Mirasvit\Rma\Model\ResourceModel\Message\CollectionFactory $messageFactory,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Mirasvit\Rma\Model\ResourceModel\Attachment\CollectionFactory $attachmentFactory,
        Filesystem $fileSystem,
        \Mirasvit\Rma\Api\Repository\AttachmentRepositoryInterface $attachmentRepository
    ) {
        $this->storeManager = $storeManager;
        $this->rmaCollectionFactory = $rmaCollectionFactory;
        $this->productCollection = $productCollection;
        $this->orderRepository = $orderRepository;
        $this->rmaManagement       = $rmaManagement;
        $this->itemModelFactory = $itemModelFactory;
        $this->_productCollectionFactory = $productCollectionFactory;   
        $this->messageFactory = $messageFactory;
        $this->_productRepository = $productRepository;
        $this->attachmentFactory = $attachmentFactory;
        $this->fileSystem = $fileSystem;
        $this->attachmentRepository = $attachmentRepository;
    }
    public function dataArray($model){
        $orderDataItem = [];
        $rmaDataItem = [];
        $rmaDataMessage = [];
	    $rmaArray = [];
        foreach ($model as $rma) {
            //get the message data of this rma
            $messages = $this->messageFactory->create()->addFieldToFilter('is_visible_in_frontend', '1')->addFieldToFilter('rma_id',$rma->getId())->getData();
            usort($messages, $this->build_sorter('message_id'));
            foreach($messages as $message){
                //search whether this messaage have attachment or not
                $attachmentsData = $this->attachmentFactory->create()->addFieldToFilter('item_id', $message['message_id']);
                $attachments = [];
                if($attachmentsData){
                    // die('123');
                    $mediaPath     = $this->fileSystem->getDirectoryRead( DirectoryList::MEDIA )->getAbsolutePath();
                    $originalPath  = 'Simicustomize/graphql/';
                    $mediaFullPath = $mediaPath . $originalPath;

                    foreach($attachmentsData as $attachmentData){
                        $uid = $attachmentData['uid'];
                        $attachment = $this->attachmentRepository->getByUid($uid);
                        $fileName = $attachment->getName();
                        // die(var_dump($fileName));
                        $fileType = $attachment->getType();
                        $attachment = [
                            'name'       => $fileName,
                            'type'       => $fileType,
                            'full_path'  => $mediaFullPath . $fileName,
                            'quote_path' => $originalPath . $fileName,
                            'order_path' => $originalPath . $fileName,
                            // 'secret_key' => substr( md5( file_get_contents( $mediaFullPath . $fileName ) ), 0, 20 )
                        ];
                        array_push($attachments, $attachment);
                    }
                }

                if(isset($message['user_id'])){
                    $message['type'] = 'admin message';
                }
                else{
                    $message['type'] = 'customer message';
                }
                $messageObject = [

                    'messaagee_id' => $message['message_id'],
                    'user_id' => isset($message['user_id'])?$message['user_id']:null,
                    'customer_name' => $message['customer_name'],
                    'type' => $message['type'],
                    'content' => $message['text'],
                    'is_read'=> $message['is_read'],
                    'items' => $attachments
                ];
                array_push($rmaDataMessage, $messageObject);
            }
            
            //get this rma return items and it's detail
            $rma_items = $this->itemModelFactory->create()->addFieldToFilter('rma_id', $rma->getId())->getData();
            foreach($rma_items as $rma_item){
                //get the message of this rma
                //end of get message for this rma
                $condition = [
                    'reason_name' => $rma_item['reason_name'],
                    'condition_name' => $rma_item['condition_name'],
                    'resolution_name' => $rma_item['resolution_name']
                ];
                $productCollection = $this->_productCollectionFactory->create();
                $productCollection->addAttributeToSelect('*');
                foreach ($productCollection as $product){
                    // die(var_dump($rma_items));
                 if($product->getData()['sku'] == $rma_item['product_sku']){
                    $productUrl = $product->getData()['image'];
                    break;
                }
            } 
            $productInfo = $this->_productRepository->get($rma_item['product_sku'])->getData();
            // die(var_dump(json_decode(json_encode($productInfo))));
            $url = $this->getBaseUrl() .'/catalog/product'. $productUrl;
            $rma_item['reason_condition'] = $condition;
            $rma_item['sku'] = $rma_item['product_sku'];
            $rma_item['url'] = $url;
            $rma_item['product_name']= $productInfo['name'];
            $rma_item['product_id'] = $productInfo['entity_id'];
            array_push($rmaDataItem, $rma_item);
        }
        //get this rma order detail
        $order = $this->rmaManagement->getOrders($rma);
        // die(var_dump(json_decode(json_encode($order))));
        foreach($this->rmaManagement->getOrders($rma) as $order)
        {
            $orderId = $order->getID();
            $orderDetail = $this->orderRepository->get($orderId);
            $orderIncrementId = $orderDetail->getIncrementId();

            foreach ($orderDetail->getAllItems() as $item) {
                $data = $item->getData();
                array_push($orderDataItem, $data);
            }

        }
        $rmaArray[$rma->getId()] = $rma->getData();
        $rmaArray[$rma->getId()]['model'] = $rma;
        $rmaArray[$rma->getId()]['order_info'] = $orderDataItem;
        $rmaArray[$rma->getId()]['return_item'] = $rmaDataItem;
        $rmaArray[$rma->getId()]['increment_id'] = $orderIncrementId;
        $rmaArray[$rma->getId()]['message'] = $rmaDataMessage;
        $rmaArray[$rma->getId()]['order_id'] = $orderId;
        $orderDataItem = [];
        $rmaDataItem = [];
        $rmaDataMessage = [];

    }

    return $rmaArray;
}

private function build_sorter($key) 
{
    return function ($a, $b) use ($key) {
        return strnatcmp((int)$a[$key], (int)$b[$key]);
    };
}
private function getBaseUrl(){
    return $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
}

}