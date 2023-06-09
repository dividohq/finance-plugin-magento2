<?php

namespace Divido\DividoFinancing\Model;

class RefundItems implements \Iterator
{
    private $key = 0;

    /**
     * Array of RefundItem objects
     *
     * @var array
     */
    private $refundItems = [];

    public function __construct(
        $refundItems = []
    ) {
        $this->refundItems = $refundItems;
        $this->key = 0;
    }

    public function current(){
        return $this->refundItems[$this->key];
    }

    public function key(){
        return $this->key;
    }

    public function next():void{
        ++$this->key;
    }

    public function rewind():void{
        $this->key = 0;
    }

    public function valid():bool{
        return isset($this->refundItems[$this->key]);
    }

    public function addRefundItem(RefundItem $item){
        $this->refundItems[] = $item;
    }

}

    