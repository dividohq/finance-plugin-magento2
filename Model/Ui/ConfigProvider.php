<?php
namespace Divido\DividoFinancing\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'divido_financing';

    private $cart;
    private $helper;

    public function __construct(
        \Magento\Checkout\Model\Cart $cart,
        \Divido\DividoFinancing\Helper\Data $helper
    ) {
    
        $this->helper = $helper;
        $this->cart  = $cart;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $quote       = $this->cart->getQuote();
        $plans       = $this->helper->getQuotePlans($quote);
        $plans       = $this->helper->plans2list($plans);
        $platformEnv = $this->helper->getPlatformEnv();
        $description = $this->helper->getDescription();

        return [
            'payment' => [
                self::CODE => [
                    'cart_plans'       => $plans,
                    'env_amount_title' =>  'data-'. $platformEnv .'-amount',
                    'env_widget_title' =>  'data-'. $platformEnv .'-widget',
                    'env_plans_title'  =>  'data-'. $platformEnv .'-plans',
                    'description' => $description,
                ]
            ]
        ];
    }
}
