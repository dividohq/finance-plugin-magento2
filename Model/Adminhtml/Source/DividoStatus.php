<?php
/**
 * Copyright © 2016 Divido. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Divido\DividoFinancing\Model\Adminhtml\Source;

/**
 * Class DividoStatus
 */
class DividoStatus implements \Magento\Framework\Option\ArrayInterface
{
    const
        STATUS_ACCEPTED = 'ACCEPTED',
        STATUS_SIGNED   = 'SIGNED';

    public function toOptionArray()
    {
        return [
            [
                'value' => self::STATUS_ACCEPTED,
                'label' => __('accepted_option'),
            ],
            [
                'value' => self::STATUS_SIGNED,
                'label' => __('signed_option'),
            ],
        ];
    }
}
