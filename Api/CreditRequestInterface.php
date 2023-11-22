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
     * @return void
     */
    public function update();

    /**
     * Responds with a json object, giving the version of
     * the platform and plugin to use for debugging
     *
     * @api
     * @return void
     */
    public function version();

}
