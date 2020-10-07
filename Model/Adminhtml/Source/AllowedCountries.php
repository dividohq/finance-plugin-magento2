<?php
/**
 * Copyright © 2016 Divido. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Divido\DividoFinancing\Model\Adminhtml\Source;

/**
 * Class PaymentAction
 */
class AllowedCountries implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            [
                'value' => 'AU',
                'label' => __('Australia'),
            ],
            [
                'value' => 'DK',
                'label' => __('Denmark'),
            ],            [
                'value' => 'FI',
                'label' => __('Finland'),
            ],
            [
                'value' => 'FR',
                'label' => __('France'),
            ],            [
                'value' => 'DE',
                'label' => __('Germany'),
            ],
            [
                'value' => 'NL',
                'label' => __('Netherlands'),
            ],            [
                'value' => 'SE',
                'label' => __('Sweden'),
            ],            [
                'value' => 'ES',
                'label' => __('Spain'),
            ],
            [
                'value' => 'GB',
                'label' => __('United Kingdom'),
            ],
        ];

        return $options;
    }
}
