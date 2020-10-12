<?php 
namespace Simi\RMAGraphQL\Model\Resolver;

use Mirasvit\Rma\Api\Config\OfflineOrderConfigInterface;

class GuestStrategy extends \Mirasvit\Rma\Helper\Controller\Rma\AbstractStrategy
{
	public $orderId;

	protected $offlineOrderFactory;
	protected $orderRepository;
	protected $customerRepository;
	protected $performerFactory;

	public function __construct(
        \Mirasvit\Rma\Model\OfflineOrderFactory $offlineOrderFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Mirasvit\Rma\Api\Service\Performer\PerformerFactoryInterface $performerFactory
    ) {
        $this->offlineOrderFactory = $offlineOrderFactory;
        $this->orderRepository = $orderRepository;
        $this->customerRepository     = $customerRepository;
        $this->performerFactory = $performerFactory;
    }
    public function setOrderId($orderId){
        $this->orderId = $orderId;
        
    }

    public function getOrderId(){
        return $this->orderId;
    }
    public function getOrder()
    {   

        $orderId = $this->getOrderId();
        // $isOfflineOrder = $this->customerSession->getRMAGuestOrderIsOffline();
       
        if ($orderId == OfflineOrderConfigInterface::OFFLINE_ORDER_PLACEHOLDER) {

            $order = $this->offlineOrderFactory->create();
            
        } else {
                $order = $this->orderRepository->get($orderId);
        }

        return $order;
    }

    public function getPerformer()
    {

        $order = $this->getOrder();
        
        $name = implode(
            ' ',
            [$order->getCustomerFirstname(), $order->getCustomerMiddlename(), $order->getCustomerLastname()]
        );

        if (empty(trim($name))) {
            if ($order->getIsOffline()) {
                if ($order->getCustomerId()) {
                    $customer = $this->customerRepository->getById($order->getCustomerId());
                    $name = implode(
                        ' ',
                        [$customer->getFirstname(), $customer->getMiddlename(), $customer->getLastname()]
                    );
                }
            } else {
                $address = $order->getBillingAddress();
                $name = implode(
                    ' ',
                    [$address->getFirstname(), $address->getMiddlename(), $address->getLastname()]
                );
            }
        }

        return $this->performerFactory->create(
            \Mirasvit\Rma\Api\Service\Performer\PerformerFactoryInterface::GUEST,
            new \Magento\Framework\DataObject(
                [
                    'name'  => $name,
                    'email' => $order->getCustomerEmail(),
                    'id'    => $order->getCustomerId(),
                ]
            )
        );
    }
    public function isRequireCustomerAutorization()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getRmaId(\Mirasvit\Rma\Api\Data\RmaInterface $rma)
    {
        return $rma->getGuestId();
    }

    /**
     * {@inheritdoc}
     */
    public function initRma(\Magento\Framework\App\RequestInterface $request)
    {
        throw new \Magento\Framework\Exception\NoSuchEntityException();
    }

    /**
     * {@inheritdoc}
     */
    public function getRmaList($order = null)
    {
        return [];
    }
    /**
     * {@inheritdoc}
     */
    public function getAllowedOrderList()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getRmaUrl(\Mirasvit\Rma\Api\Data\RmaInterface $rma)
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getNewRmaUrl()
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getPrintUrl(\Mirasvit\Rma\Api\Data\RmaInterface $rma)
    {
        return '';
    }
}


 ?>
