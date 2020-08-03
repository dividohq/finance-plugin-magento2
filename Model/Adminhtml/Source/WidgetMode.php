<?php
/**
 * Copyright © 2019 Divido. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Divido\DividoFinancing\Model\Adminhtml\Source;

/**
 * Class WidgetMode
 */
class WidgetMode implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'lightbox',
                'label' => __('lightbox_option'),
            ],
            [
                'value' => 'calculator',
                'label' => __('calculator_option'),
            ],
        ];
    }
}
