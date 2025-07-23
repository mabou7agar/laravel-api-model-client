<?php

namespace ApiModelRelations\Services\Transformers;

use ApiModelRelations\Contracts\ResponseTransformerInterface;

abstract class AbstractResponseTransformer implements ResponseTransformerInterface
{
    /**
     * The next transformer in the chain.
     *
     * @var \ApiModelRelations\Contracts\ResponseTransformerInterface|null
     */
    protected $next;

    /**
     * Transform the API response.
     *
     * @param mixed $response
     * @return mixed
     */
    public function transform($response)
    {
        // Apply this transformer's logic
        $transformedResponse = $this->doTransform($response);
        
        // Pass to the next transformer in the chain if available
        if ($this->next !== null) {
            return $this->next->transform($transformedResponse);
        }
        
        return $transformedResponse;
    }
    
    /**
     * Set the next transformer in the chain.
     *
     * @param \ApiModelRelations\Contracts\ResponseTransformerInterface $transformer
     * @return $this
     */
    public function setNext(ResponseTransformerInterface $transformer)
    {
        $this->next = $transformer;
        return $this;
    }
    
    /**
     * Apply the transformation logic specific to this transformer.
     *
     * @param mixed $response
     * @return mixed
     */
    abstract protected function doTransform($response);
}
