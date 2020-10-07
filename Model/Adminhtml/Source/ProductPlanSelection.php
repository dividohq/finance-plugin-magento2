<?php
/**
 * Copyright © 2016 Divido. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Divido\DividoFinancing\Model\Adminhtml\Source;

/**
 * Class PaymentAction
 */
class ProductPlanSelection extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    public function __construct(
        \Divido\DividoFinancing\Helper\Data $helper,
        \Psr\Log\LoggerInterface $logger
    ) {

        $this->helper = $helper;
        $this->logger = $logger;
    }

    public function getAllOptions()
    {
        $plans = [];

        try {
            $plans = $this->helper->getAllPlans();
        } catch (\Exception $e) {
            $this->logger->addError($e);
        }

        $options = [];
        foreach ($plans as $plan) {
            $options[] = [
                'value' => $plan->id,
                'label' => $plan->description,
            ];
        }

        return $options;
    }
}
