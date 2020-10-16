<?php

namespace Simi\RMAGraphQL\Model\Resolver;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class SendMessage implements ResolverInterface {

	protected $messageDataProvider;
	protected $rmaDataProvider;
	protected $rmaCollectionFactory;
	protected $customerStrategy;
	protected $guestStrategy;
	protected $messageRepository;
	protected $rmaRepository;
	protected $filterFactory;
	protected $urlModel;
	protected $rmaMail;
	private $fileSystem;
	public $attachmentRepository;
	protected $config;

	public function __construct(
		\Simi\RMAGraphQL\Model\DataProvider\RmaDataArray $rmaDataProvider,
		\Simi\RMAGraphQL\Model\DataProvider\GetMessageData $messageDataProvider,
		\Mirasvit\Rma\Helper\Controller\Rma\CustomerStrategy $customerStrategy,
		GuestStrategy $guestStrategy,
		\Mirasvit\Rma\Model\ResourceModel\Rma\CollectionFactory $rmaCollectionFactory,
		\Mirasvit\Rma\Api\Repository\MessageRepositoryInterface $messageRepository,
		\Mirasvit\Rma\Api\Repository\RmaRepositoryInterface $rmaRepository,
		\Magento\Email\Model\Template\FilterFactory $filterFactory,
		\Magento\Framework\UrlInterface $urlModel,
		\Mirasvit\Rma\Helper\Mail $rmaMail,
		Filesystem $fileSystem,
		\Mirasvit\Rma\Api\Repository\AttachmentRepositoryInterface $attachmentRepository,
		\Mirasvit\Rma\Api\Config\AttachmentConfigInterface $config

	) {
		$this->rmaDataProvider      = $rmaDataProvider;
		$this->messageDataProvider  = $messageDataProvider;
		$this->rmaCollectionFactory = $rmaCollectionFactory;
		$this->customerStrategy     = $customerStrategy;
		$this->guestStrategy        = $guestStrategy;
		$this->messageRepository    = $messageRepository;
		$this->rmaRepository        = $rmaRepository;
		$this->filterFactory        = $filterFactory;
		$this->urlModel             = $urlModel;
		$this->rmaMail              = $rmaMail;
		$this->fileSystem           = $fileSystem;
		$this->attachmentRepository = $attachmentRepository;
		$this->config               = $config;
	}

	public function resolve( Field $field, $context, ResolveInfo $info, array $value = null, array $args = null ) {
		if ( empty( $args['guest_id'] ) ) {
			throw new GraphQlInputException( __( 'You must specify your "RMA Id".' ) );
		}

		try {
			$rma       = $this->getRma( $args['guest_id'] );
			$params    = [
				'isNotifyAdmin' => 1,
				'isNotified'    => 0,
			];
			$message   = $args['content'];
			$performer = $this->getPerfomer( $context, $this->getOrderId( $args['guest_id'] ) );

			$this->addMessage(
				$performer,
				$rma,
				$message,
				$params
			);

			//return the messages of this rma
			$lastMessageCollection = $this->messageRepository->create()->getCollection()->getFirstItem();

			if ( isset( $args['input'] ) ) {

				//handling exception
				if ( empty( $args['input'] ) || ! is_array( $args['input'] ) || ! count( $args['input'] ) ) {
					throw new GraphQlInputException( __( 'You must specify your input.' ) );
				}

				$mediaPath     = $this->fileSystem->getDirectoryRead( DirectoryList::MEDIA )->getAbsolutePath();
				$originalPath  = 'Simicustomize/graphql/';
				$mediaFullPath = $mediaPath . $originalPath;
				if ( ! file_exists( $mediaFullPath ) ) {
					mkdir( $mediaFullPath, 0775, true );
				}

				$fileSizeLimit         = (float) $this->config->getFileSizeLimit() * 1024 * 1024;
				$allowedFileExtensions = $this->config->getFileAllowedExtensions();
				foreach ( $args['input'] as $inputItem ) {
					if ( empty( $inputItem['name'] ) ) {
						throw new GraphQlInputException( __( 'You must specify your "file name".' ) );
					}

					if ( empty( $inputItem['base64_encoded_file'] ) ) {
						throw new GraphQlInputException( __( 'You must specify your "file".' ) );
					}

					$fileType = isset( $inputItem['type'] ) ? $inputItem['type'] : '';
					$fileName = rand() . time() . '_' . $inputItem['name'];
					$ext      = pathinfo( $fileName, PATHINFO_EXTENSION );
					if ( count( $allowedFileExtensions ) && ! in_array( strtolower( $ext ), $allowedFileExtensions ) ) {
						continue;
					}

					if ( $fileSizeLimit && $inputItem['size'] > $fileSizeLimit ) {
						continue;
					}

					$base64FileArray = explode( ',', $inputItem['base64_encoded_file'] );

					$fileContent = base64_decode( $base64FileArray[0] );

					$savedFile = fopen( $mediaFullPath . $fileName, "wb" );
					fwrite( $savedFile, $fileContent );
					fclose( $savedFile );

					$this->_saveFile( "message", $lastMessageCollection->getId(), $fileName, $fileContent, $fileType, $inputItem['size'] );
				}
			}

			return $this->getMessageData( $lastMessageCollection->getRmaId() );
		}
		catch ( \Exception $e ) {
			throw new GraphQlNoSuchEntityException( __( 'Error. Uploaded file does not match requirements.' ) );
		}
	}

	private function getMessageData( $rmaId ) {
		return $this->messageDataProvider->dataArray( $rmaId );
	}

	private function getThisRmaData( $guestId ) {
		$collection = $this->rmaCollectionFactory->create()->addFieldToFilter( 'guest_id', $guestId )->getItems();
		$dataArrays = $this->rmaDataProvider->dataArray( $collection );
		foreach ( $dataArrays as $dataArray ) {
			if ( $dataArray['guest_id'] == $guestId ) {
				return $dataArray;
			}
		}
	}


	private function getOrderId( $guestId ) {
		return $this->getThisRmaData( $guestId )['order_id'];
	}

	private function getRma( $incrementId ) {
		$model = $this->rmaCollectionFactory->create()->getItems();

		$count = 0;
		foreach ( $model as $dataArray ) {
			if ( $dataArray['guest_id'] == $incrementId ) {
				$rma = $dataArray;
				$count ++;

				return $rma;
			}
		}
		if ( $count == 0 ) {
			throw new \Magento\Framework\Exception\LocalizedException( __( 'No Rma Found' ) );
		}
	}

	private function getPerfomer( $context, $orderId ) {
		try {
			$this->guestStrategy->setOrderId( $orderId );

			return $this->guestStrategy->getPerformer();
		}
		catch ( \Magento\Framework\Exception\NoSuchEntityException $e ) {
			return $this->noAccessStrategy->getPerformer();
		}
	}

	public function addMessage(
		\Mirasvit\Rma\Api\Service\Performer\PerformerInterface $performer,
		\Mirasvit\Rma\Api\Data\RmaInterface $rma,
		$messageText,
		$params = []
	) {
		if ( ! $messageText && ! $this->attachmentManagement->hasAttachments() ) {
			throw new \Magento\Framework\Exception\LocalizedException( __( 'Please, enter a message' ), null, 400 );
		}
		/** @var MessageInterface $message */
		$message = $this->messageRepository->create();
		$message
			->setRmaId( $rma->getId() )
			->setText( $this->processVariables( $messageText, $rma ) );
		// die('1234');
		if ( isset( $params['statusChanged'] ) && $params['statusChanged'] ) {
			$message->setStatusId( $rma->getStatusId() );
		}

		$performer->setMessageAttributesBeforeAdd( $message, $params );
		$this->messageRepository->save( $message );

		$rma->setLastReplyName( $performer->getName() )
		    ->setIsAdminRead( $performer instanceof UserStrategy );

		$this->rmaRepository->save( $rma );

	}

	private function processVariables( $text, $rma ) {
		$templateFilter = $this->filterFactory->create();
		$templateFilter->setUseAbsoluteLinks( true )
		               ->setStoreId( $rma->getStoreId() )
		               ->setUrlModel( $this->urlModel )
		               ->setPlainTemplateMode( true )
		               ->setVariables( $this->rmaMail->getEmailVariables( $rma ) );

		return $templateFilter->filter( $text );
	}

	public function _saveFile( $itemType, $itemId, $name, $body, $fileType, $size, $isReplace = false ) {
		/** @var \Mirasvit\Rma\Model\Attachment $attachment */
		$attachment = false;
		if ( $isReplace ) {
			$attachment = $this->getAttachment( $itemType, $itemId );
		}

		if ( ! $attachment ) {
			$attachment = $this->attachmentRepository->create();
		}
		set_error_handler( function () { /* ignore errors */
		} );
		//@tofix - need to check for max upload size and alert error

		$attachment
			->setItemType( $itemType )
			->setItemId( $itemId )
			->setName( $name )
			->setSize( $size )
			->setBody( $body )
			->setType( $fileType )
			->save();
	}

}
