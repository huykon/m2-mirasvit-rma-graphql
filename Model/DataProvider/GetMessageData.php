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


class GetMessageData {
	protected $orderCollectionFactory;
	protected $itemModelFactory;
	protected $messageFactory;
	protected $attachmentFactory;
	protected $fileSystem;
	protected $attachmentRepository;
	protected $storeManager;

	public function __construct(
		\Mirasvit\Rma\Model\ResourceModel\Item\CollectionFactory $itemModelFactory,
		\Mirasvit\Rma\Model\ResourceModel\Message\CollectionFactory $messageFactory,
		\Mirasvit\Rma\Model\ResourceModel\Attachment\CollectionFactory $attachmentFactory,
		Filesystem $fileSystem,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Mirasvit\Rma\Api\Repository\AttachmentRepositoryInterface $attachmentRepository
	) {
		$this->itemModelFactory     = $itemModelFactory;
		$this->messageFactory       = $messageFactory;
		$this->attachmentFactory    = $attachmentFactory;
		$this->fileSystem           = $fileSystem;
		$this->attachmentRepository = $attachmentRepository;
		$this->storeManager         = $storeManager;
	}

	//@param: the array of the id of orders to return
	public function dataArray( $rma_id ) {
		try {
			$storeBaseUrl   = $this->storeManager->getStore()->getBaseUrl();
			$rmaDataMessage = [];
			$messages       = $this->messageFactory->create()->addFieldToFilter( 'is_visible_in_frontend', '1' )->addFieldToFilter( 'rma_id', $rma_id )->getData();

			foreach ( $messages as $message ) {
				//search whether this message have attachment or not
				$attachmentsData = $this->attachmentFactory->create()->addFieldToFilter( 'item_id', $message['message_id'] );
				$attachments     = [];
				if ( $attachmentsData ) {
					$mediaPath     = $this->fileSystem->getDirectoryRead( DirectoryList::MEDIA )->getAbsolutePath();
					$originalPath  = 'Simicustomize/graphql/';
					$mediaFullPath = $mediaPath . $originalPath;

					foreach ( $attachmentsData as $attachmentData ) {
						$uid                     = $attachmentData['uid'];
						$attachment              = $this->attachmentRepository->getByUid( $uid );
						$fileName                = $attachment->getName();
						$fileType                = $attachment->getType();
						$attachmentControllerUrl = $storeBaseUrl . 'returns/attachment/download/uid/' . $uid;

						$attachment    = [
							'name'       => $fileName,
							'type'       => $fileType,
							'full_path'  => $mediaFullPath . $fileName,
							'quote_path' => $originalPath . $fileName,
							'order_path' => $originalPath . $fileName,
							'link'       => $attachmentControllerUrl
							// 'secret_key' => substr( md5( file_get_contents( $mediaFullPath . $fileName ) ), 0, 20 )
						];
						$attachments[] = $attachment;
					}
				}

				$messageObject    = [
					'message_id'    => $message['message_id'],
					'user_id'       => $message['user_id'],
					'customer_name' => $message['customer_name'],
					'customer_id'   => $message['customer_id'],
					'content'       => $message['text'],
					'is_read'       => $message['is_read'],
					'created_at'    => $message['created_at'],
					'updated_at'    => $message['updated_at'],
					'items'         => $attachments
				];
				$rmaDataMessage[] = $messageObject;
			}

			return $rmaDataMessage;
		}
		catch ( \Magento\Framework\Exception\LocalizedException $e ) {
			throw new \Magento\Framework\Exception\LocalizedException( __( 'no rma has this id' ) );
		}
	}
}
