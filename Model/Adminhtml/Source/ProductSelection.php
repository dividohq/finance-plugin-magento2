<?php
/**
 * Copyright © 2016 Divido. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Divido\DividoFinancing\Model\Adminhtml\Source;

/**
 * Class ProductSelection
 */
class ProductSelection implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'products_all',
                'label' => __('finance_all_products_option'),
            ],
            [
                'value' => 'products_selected',
                'label' => __('finance_specific_products_option'),
            ],
            [
                'value' => 'products_price_threshold',
                'label' => __('finance_threshold_products_option'),
            ],
        ];
    }
}
