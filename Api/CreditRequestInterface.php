<?php

namespace Divido\DividoFinancing\Api;

interface CreditRequestInterface
{
    /**
     * Create a credit request at Divido, return a URL to complete the credit
     * request.
     *
     * @api
     * @param string Quote ID
     * @return string Credit request URL
     */
    public function create();

    /**
     * Update an order with results from credit request
     *
     * @api
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function update();

    /**
     * Update an order with results from credit request
     *
     * @api
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function version();

}
