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
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;

class SendMessage implements ResolverInterface 
{

    protected $dataProvider;
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
        \Simi\RMAGraphQL\Model\DataProvider\RmaDataArray $dataProvider,
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
        $this->dataProvider = $dataProvider;
        $this->rmaCollectionFactory = $rmaCollectionFactory;
        $this->customerStrategy = $customerStrategy;
        $this->guestStrategy = $guestStrategy;
        $this->messageRepository     = $messageRepository;
        $this->rmaRepository         = $rmaRepository;
        $this->filterFactory         = $filterFactory;
        $this->urlModel              = $urlModel;
        $this->rmaMail               = $rmaMail;
        $this->fileSystem = $fileSystem;
        $this->attachmentRepository = $attachmentRepository;
        $this->config                = $config;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {

        $rma = $this->getRma($args['guest_id']);
        $params = [
            'isNotifyAdmin' => 1,
            'isNotified'    => 0,
        ];
        $message = $args['content'];
        $performer = $this->getPerfomer($context, $this->getOrderId($args['guest_id']));
        $this->addMessage(
            $performer,
            $rma,
            $message,
            $params
        );
        //return the messages of this rma
        
        if(isset($args['input'])){
            {
                //handling exception
                if ( empty( $args['input'] ) || ! is_array( $args['input'] ) || ! count( $args['input'] ) ) {
                    throw new GraphQlInputException( __( 'You must specify your input.' ) );
                }
                foreach($args['input'] as $input){
                    if ( empty( $input['name'] ) ) {
                        throw new GraphQlInputException( __( 'You must specify your "file name".' ) );
                    }

                    if ( empty( $input['base64_encoded_file'] ) ) {
                        throw new GraphQlInputException( __( 'You must specify your "file".' ) );
                    }
                }
                
                $mediaPath     = $this->fileSystem->getDirectoryRead( DirectoryList::MEDIA )->getAbsolutePath();
                $originalPath  = 'Simicustomize/graphql/';
                $mediaFullPath = $mediaPath . $originalPath;
                if ( ! file_exists( $mediaFullPath ) ) {
                    mkdir( $mediaFullPath, 0775, true );
                }

                $arrayReturn = [ 'items' => null ];

                $fileSizeLimit = (float)$this->config->getFileSizeLimit() * 1024 * 1024;
                $allowedFileExtensions = $this->config->getFileAllowedExtensions();

                foreach ( $args['input'] as $input ) {
                    $fileType      = isset( $input['type'] ) ? $input['type'] : '';
                    $fileName        = rand() . time() . '_' . $input['name'];
                    $ext  = pathinfo($fileName, PATHINFO_EXTENSION);
                    if (count($allowedFileExtensions) && !in_array(strtolower($ext), $allowedFileExtensions)) {
                        $error = true;
                        continue;
                    }

                    if ($fileSizeLimit && $size > $fileSizeLimit) {
                        $error = true;
                        continue;
                    }
                    die(var_dump($error));
                    

                    $base64FileArray = explode( ',', $input['base64_encoded_file'] );

                    $fileContent = base64_decode($base64FileArray[0]);
                // die(var_dump(get_class_methods($fileBlob)));
                    $savedFile   = fopen( $mediaFullPath . $fileName, "wb" );
                    fwrite( $savedFile, $fileContent );
                    fclose( $savedFile );
                    $arrayReturn['items'][] = [
                        'name'       => $fileName,
                        'type'       => $fileType,
                        'full_path'  => $mediaFullPath . $fileName,
                        'quote_path' => $originalPath . $fileName,
                        'order_path' => $originalPath . $fileName,
                        'secret_key' => substr( md5( file_get_contents( $mediaFullPath . $fileName ) ), 0, 20 )
                    ];
                }
                if(!$error){
                    $this->_saveFile("message", $this->getLatestId($args['guest_id']), $fileName, $fileContent, $fileType, $input['size']);
                }
                else{

                    throw new \Magento\Framework\Exception\LocalizedException(__('Error. Uploaded file does not match requirements.'));
                    
                }
            
            }
        }
        return $this->getReturnMessage($args['guest_id']);
    } 

    private function getThisRmaData($guestId){
        $model = $this->rmaCollectionFactory->create()->getItems();
        $dataArrays = $this->dataProvider->dataArray($model);
        foreach($dataArrays as $dataArray){
            if($dataArray['guest_id'] == $guestId){
                return $dataArray;
            }
        } 
    }

    private function getReturnMessage($guestId){
        return $this->getThisRmaData($guestId)['message'];
    }

    private function getLatestId($guestId){
        $arrays = $this->getThisRmaData($guestId)['message'];
        $lastmessage =  end($arrays);
        return $lastmessage['message_id'];
    }

    private function getOrderId($guestId){
        return $this->getThisRmaData($guestId)['order_id'];
    }

    private function getRma($incrementId){
        $model = $this->rmaCollectionFactory->create()->getItems();

        $count = 0;
        foreach($model as $dataArray){
            if($dataArray['guest_id'] == $incrementId){
                $rma = $dataArray;
                $count++;
                return $rma;
            }
        }
        if($count == 0){
            throw new \Magento\Framework\Exception\LocalizedException(__('No Rma Found'));
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

    public function addMessage(
        \Mirasvit\Rma\Api\Service\Performer\PerformerInterface $performer,
        \Mirasvit\Rma\Api\Data\RmaInterface $rma,
        $messageText,
        $params = []
    ){
        if (!$messageText && ! $this->attachmentManagement->hasAttachments()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Please, enter a message'), null, 400);
        }
        /** @var MessageInterface $message */
        $message = $this->messageRepository->create();
        $message
        ->setRmaId($rma->getId())
        ->setText($this->processVariables($messageText, $rma));
        // die('1234');
        if (isset($params['statusChanged']) && $params['statusChanged']) {
            $message->setStatusId($rma->getStatusId());
        }

        $performer->setMessageAttributesBeforeAdd($message, $params);
        $this->messageRepository->save($message);

        $rma->setLastReplyName($performer->getName())
        ->setIsAdminRead($performer instanceof UserStrategy);

        $this->rmaRepository->save($rma);

    }

    private function processVariables($text, $rma)
    {
        $templateFilter = $this->filterFactory->create();
        $templateFilter->setUseAbsoluteLinks(true)
        ->setStoreId($rma->getStoreId())
        ->setUrlModel($this->urlModel)
        ->setPlainTemplateMode(true)
        ->setVariables($this->rmaMail->getEmailVariables($rma))
        ;

        return $templateFilter->filter($text);
    }

    public function _saveFile($itemType, $itemId, $name, $body, $fileType, $size, $isReplace = false)
    {
        /** @var \Mirasvit\Rma\Model\Attachment $attachment */
        $attachment = false;
        if ($isReplace) {
            $attachment = $this->getAttachment($itemType, $itemId);
        }

        if (!$attachment) {
            $attachment = $this->attachmentRepository->create();
        }
    set_error_handler(function() { /* ignore errors */ });
        //@tofix - need to check for max upload size and alert error

    $attachment
    ->setItemType($itemType)
    ->setItemId($itemId)
    ->setName($name)
    ->setSize($size)
    ->setBody($body)
    ->setType($fileType)
    ->save();
}

}
