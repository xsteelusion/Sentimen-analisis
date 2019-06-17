<?php

declare(strict_types=1);

namespace Phpml\FeatureExtraction;

use Phpml\Transformer;

class TfIdfTransformer implements Transformer
{
    /**
     * @var array
     */
    private $idf;

    /**
     * @param array $samples
     */
    public function __construct(array $samples = null)
    {
        if ($samples) {
            $this->fit($samples);
        }
    }

    /**
     * @param array $samples
     */
    public function fit(array $samples)
    {
        $this->countTokensFrequency($samples);

        $count = count($samples);
        foreach ($this->idf as &$value) {
            if($value == 0){
                $value = 0;
            }else{
                $value = log((float)($count / $value), 10.0);                
            }
        }
    }

    /**
     * @param array $samples
     */
    public function transform(array &$samples)
    {
        foreach ($samples as &$sample) {
            foreach ($sample as $index => &$feature) {
                $feature *= $this->idf[$index];
            }
        }
    }

    /**
     * @param array $samples
     */
    private function countTokensFrequency(array $samples)
    {
        $this->idf = array_fill_keys(array_keys(reset($samples)), 0);

        foreach ($samples as $sample) {
            foreach ($sample as $index => $count) {
                if ($count > 0) {
                    ++$this->idf[$index];
                }
            }
        }
    }
}
