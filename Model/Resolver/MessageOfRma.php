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



class MessageOfRma implements ResolverInterface 
{

    protected $dataProvider;
    
    
    public function __construct(
        \Simi\RMAGraphQL\Model\DataProvider\GetMessageData $dataProvider
        
        
    ) {
        $this->dataProvider = $dataProvider;
       
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        try
        {   
            return $this->dataProvider->dataArray($args['rma_id']);
        }
        catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__('no rma has this id'));
        }
        
    }
}
