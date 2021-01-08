<?php

namespace Divido\DividoFinancing\Model;

use Psr\Log\LoggerInterface;
use Magento\Framework\TranslateInterface;


class Translate extends \Magento\Framework\Phrase\Renderer\Translate
{

    /**
     * @var \Magento\Framework\File\Csv
     */
    protected $_csvParser;

    public function __construct(
        TranslateInterface $translator,
        LoggerInterface $logger,
        \Magento\Framework\File\Csv $csvParser
    ) {
        $this->_csvParser = $csvParser;
        parent::__construct($translator, $logger);
    }

    public function render(array $source, array $arguments){
        $text = end($source);
        /* If phrase contains escaped quotes then use translation for phrase with non-escaped quote */
        $text = strtr($text, ['\"' => '"', "\\'" => "'"]);

        try {
            $data = $this->translator->getData();
            $fallbackData = $this->_getFileData(__DIR__.'/../i18n/en_GB.csv');
            $data = array_merge($fallbackData, $data);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            throw $e;
        }

        return array_key_exists($text, $data) ? $data[$text] : end($source);
        
    }

    /**
     * Retrieve data from file
     *
     * @param string $file
     * @return array
     */
    protected function _getFileData($file)
    {
        $data = [];
        $this->_csvParser->setDelimiter(',');
        $data = $this->_csvParser->getDataPairs($file);
    
        return $data;
    }
}