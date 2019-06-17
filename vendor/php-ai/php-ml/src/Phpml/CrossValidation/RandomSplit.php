<?php

declare(strict_types=1);

namespace Phpml\CrossValidation;

use Phpml\Dataset\Dataset;

class RandomSplit extends Split
{
    /**
     * @param Dataset $dataset
     * @param float   $testSize
     */
    protected function splitDataset(Dataset $dataset, float $testSize)
    {
        $samples = $dataset->getSamples();
        $labels = $dataset->getTargets();
        $datasetSize = count($samples);
        $testCount = count($this->testSamples);

        $i = 0;
        foreach ($samples as $key => $value) {
            $samplesRandom[$i]['sample'] = $value;
            $samplesRandom[$i]['id'] = $key;
            $i++;
        }

        $i = 0;
        foreach ($labels as $key => $value) {
            $labelsRandom[$i]['label'] = $value;
            $i++;
        }

        for ($i = $datasetSize; $i > 0; --$i) {
            $key = mt_rand(0, $datasetSize - 1);
            $setName = (count($this->testSamples) - $testCount) / $datasetSize >= $testSize ? 'train' : 'test';

            $this->{$setName.'Samples'}[] = $samplesRandom[$key]['sample'];
            $this->{$setName.'Labels'}[] = $labelsRandom[$key]['label'];
            $this->{$setName.'IDs'}[] = $samplesRandom[$key]['id'];
        }
    }
}
