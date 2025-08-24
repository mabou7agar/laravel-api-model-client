<?php

namespace MTechStack\LaravelApiModelClient\Services\Transformers;

class DataWrapperTransformer extends AbstractResponseTransformer
{
    /**
     * The key to look for in the response.
     *
     * @var string
     */
    protected $wrapperKey;

    /**
     * Create a new data wrapper transformer instance.
     *
     * @param string $wrapperKey
     * @return void
     */
    public function __construct(string $wrapperKey = 'data')
    {
        $this->wrapperKey = $wrapperKey;
    }

    /**
     * Apply the transformation logic specific to this transformer.
     *
     * @param mixed $response
     * @return mixed
     */
    protected function doTransform($response)
    {
        // If response is not an array or doesn't have the wrapper key, return as is
        if (!is_array($response) || !isset($response[$this->wrapperKey])) {
            return $response;
        }

        // Extract the data from the wrapper
        return $response[$this->wrapperKey];
    }

    /**
     * Get the name of this transformer.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'data_wrapper';
    }
}
