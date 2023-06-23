<?php
namespace Divido\DividoFinancing\Observer;

use Divido\DividoFinancing\Exceptions\CancelException;
use Magento\Framework\Event\ObserverInterface;

class CancelObserver implements ObserverInterface
{
    public $_request;
    public $helper;
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Divido\DividoFinancing\Helper\Data $helper
    ) {
        $this->_request = $request;
        $this->helper = $helper;
    }
    public function execute(\Magento\Framework\Event\Observer $observer)
    {

        $order = $observer->getEvent()->getOrder();

        $params = $this->_request->getParams();

        if(isset($params['pbd_notify']) && $params['pbd_notify'] == true){
            
            try{
                $application = $this->helper->getApplicationFromOrder($order);
            } catch (\Divido\MerchantSDK\Exceptions\MerchantApiBadResponseException $_){
                throw new CancelException(__("It appears the cancellation was created with a different API key to the one currently in use. Pleae revert to that API Key if you wish to notify the lender"));
            }

            $reason = (isset($params['pbd_reason'])) ? $params['pbd_reason'] : null;
            
            if(array_key_exists($application['lender']['app_name'], Data::REFUND_CANCEL_REASONS) && $reason == null){
                throw new CancelException(__("You must specify a cancellation reason"));
            }
        
            return $this->helper->autoCancel($order, $reason);
        }
    }
}
