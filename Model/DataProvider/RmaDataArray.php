<?php

namespace Simi\RMAGraphQL\Model\DataProvider;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;


class RmaDataArray {
	protected $storeManager;
	protected $rmaCollectionFactory;
	protected $productCollection;
	protected $orderRepository;
	protected $rmaManagement;
	protected $itemModelFactory;
	protected $_productCollectionFactory;
	protected $_productRepository;
	protected $statusCollection;
	protected $_productRepositoryFactory;
	protected $statusRepository;
	protected $mailHelper;
	protected $currency;

	public function __construct(
		CollectionFactory $productCollection,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Catalog\Model\Category $categoryModel,
		\Mirasvit\Rma\Model\ResourceModel\Rma\CollectionFactory $rmaCollectionFactory,
		\Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
		\Mirasvit\Rma\Api\Service\Rma\RmaManagementInterface $rmaManagement,
		\Mirasvit\Rma\Model\ResourceModel\Item\CollectionFactory $itemModelFactory,
		\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactor,
		\Magento\Catalog\Model\ProductRepository $productRepository,
		\Mirasvit\Rma\Model\ResourceModel\Status\CollectionFactory $statusCollection,
		\Magento\Catalog\Api\ProductRepositoryInterfaceFactory $productRepositoryFactory,
		\Mirasvit\Rma\Api\Repository\StatusRepositoryInterface $statusRepository,
		\Mirasvit\Rma\Helper\Mail $mailHelper,
		\Magento\Directory\Model\Currency $currency
	) {
		$this->_productRepositoryFactory = $productRepositoryFactory;
		$this->storeManager              = $storeManager;
		$this->rmaCollectionFactory      = $rmaCollectionFactory;
		$this->productCollection         = $productCollection;
		$this->orderRepository           = $orderRepository;
		$this->rmaManagement             = $rmaManagement;
		$this->itemModelFactory          = $itemModelFactory;
		$this->_productCollectionFactory = $productCollectionFactory;
		$this->_productRepository        = $productRepository;
		$this->attachmentFactory         = $attachmentFactory;
		$this->fileSystem                = $fileSystem;
		$this->attachmentRepository      = $attachmentRepository;
		$this->statusCollection          = $statusCollection;
		$this->statusRepository          = $statusRepository;
		$this->mailHelper                = $mailHelper;
		$this->currency                  = $currency;
	}

	public function dataArray( $model ) {
		$orderDataItem  = [];
		$rmaDataItem    = [];
		$rmaDataMessage = [];
		$rmaArray       = [];
		$grandTotals    = '';
		$orderIncrementIds = [];
		$storeBaseUrl   = $this->storeManager->getStore()->getBaseUrl();

		foreach ( $model as $rma ) {
			
			//get this rma return items and it's detail
			$rma_items = $this->itemModelFactory->create()->addFieldToFilter( 'rma_id', $rma->getId() )->getData();
			foreach ( $rma_items as $rma_item ) {
				//get the message of this rma
				//end of get message for this rma
				$condition         = [
					'reason_name'     => $rma_item['reason_name'],
					'condition_name'  => $rma_item['condition_name'],
					'resolution_name' => $rma_item['resolution_name']
				];
				$productCollection = $this->_productCollectionFactory->create();
				$productCollection->addAttributeToSelect( '*' );
				foreach ( $productCollection as $product ) {
					// die(var_dump($rma_items));
					if ( $product->getData()['sku'] == $rma_item['product_sku'] ) {
						$productUrl = $product->getData()['image'];
						break;
					}
				}
				$productInfo = $this->_productRepository->get( $rma_item['product_sku'] )->getData();
				// die(var_dump(json_decode(json_encode($productInfo))));
				$url                          = $this->getBaseUrl() . '/catalog/product' . $productUrl;
				$rma_item['reason_condition'] = $condition;
				$rma_item['sku']              = $rma_item['product_sku'];
				$rma_item['url']              = $url;
				$rma_item['product_name']     = $productInfo['name'];
				$rma_item['product_id']       = $productInfo['entity_id'];
				array_push( $rmaDataItem, $rma_item );
			}
			//get this rma order detail
			$order = $this->rmaManagement->getOrders( $rma );

			foreach ( $this->rmaManagement->getOrders( $rma ) as $order ) {

				$orderId           = $order->getID();
				$orderDetail       = $this->orderRepository->get( $orderId );
				$orderIncrementId  = $orderDetail->getIncrementId();
				array_push($orderIncrementIds, $orderIncrementId);
				$productCollection = $this->_productCollectionFactory->create();
				$productCollection->addAttributeToSelect( '*' );
				$grandTotals = [ 'value'    => (float) $order->getGrandTotal(),
				                 'currency' => $order->getOrderCurrencyCode()
				];

				foreach ( $orderDetail->getAllItems() as $item ) {
					$data = $item->getData();
					$orderDetail       = $this->orderRepository->get( $data['order_id'] );
					$orderIncrementId  = $orderDetail->getIncrementId();
					$product = $this->_productRepositoryFactory->create()->getById( $data['product_id'] );
					$productUrl  = $product->getData()['image'];
					$data['url'] = $this->getBaseUrl() . '/catalog/product' . $productUrl;
					$data['order_increment_id'] = $orderIncrementId;
					$data['product_name'] = $data['name'];
					array_push( $orderDataItem, $data );
				}

			}
			$status          = $this->rmaManagement->getStatus( $rma );
			$message         = $this->statusRepository->getHistoryMessageForStore( $status, $rma->getStoreId() );
			$history_message = $this->mailHelper->parseVariables( $message, $rma );

			$rmaArray[ $rma->getId() ]                     = $rma->getData();
			$rmaArray[ $rma->getId() ]['model']            = $rma;
			$rmaArray[ $rma->getId() ]['order_info']       = $orderDataItem;
			$rmaArray[ $rma->getId() ]['return_item']      = $rmaDataItem;
			$rmaArray[ $rma->getId() ]['increment_id']     = isset($orderIncrementIds)?$orderIncrementIds:"offline";
			$rmaArray[ $rma->getId() ]['message']          = $rmaDataMessage;
			$rmaArray[ $rma->getId() ]['order_id']         = isset($orderId)?$orderId:null;
			$rmaArray[ $rma->getId() ]['rma_increment_id'] = $rma['increment_id'];
			$rmaArray[ $rma->getId() ]['create_at']        = $rma['created_at'];
			$rmaArray[ $rma->getId() ]['history_message']  = $history_message;
			$rmaArray[ $rma->getId() ]['grand_totals']     = $grandTotals;

			$grandTotals    = "";
			$orderDataItem  = [];
			$rmaDataItem    = [];
			$rmaDataMessage = [];
			$orderIncrementIds = [];

		}

		return $rmaArray;
	}

	private function getBaseUrl() {
		return $this->storeManager->getStore()->getBaseUrl( \Magento\Framework\UrlInterface::URL_TYPE_MEDIA );
	}
	
}