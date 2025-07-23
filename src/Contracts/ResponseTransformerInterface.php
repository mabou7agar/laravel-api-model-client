<?php

namespace ApiModelRelations\Contracts;

interface ResponseTransformerInterface
{
    /**
     * Transform the API response.
     *
     * @param mixed $response
     * @return mixed
     */
    public function transform($response);
    
    /**
     * Get the name of this transformer.
     *
     * @return string
     */
    public function getName(): string;
    
    /**
     * Set the next transformer in the chain.
     *
     * @param \ApiModelRelations\Contracts\ResponseTransformerInterface $transformer
     * @return $this
     */
    public function setNext(ResponseTransformerInterface $transformer);
}
