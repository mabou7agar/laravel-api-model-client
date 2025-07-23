<?php

namespace ApiModelRelations\Services\Transformers;

class KeyMappingTransformer extends AbstractResponseTransformer
{
    /**
     * The key mapping array.
     *
     * @var array
     */
    protected $mapping;

    /**
     * Whether to remove unmapped keys.
     *
     * @var bool
     */
    protected $removeUnmapped;

    /**
     * Create a new key mapping transformer instance.
     *
     * @param array $mapping
     * @param bool $removeUnmapped
     * @return void
     */
    public function __construct(array $mapping, bool $removeUnmapped = false)
    {
        $this->mapping = $mapping;
        $this->removeUnmapped = $removeUnmapped;
    }

    /**
     * Apply the transformation logic specific to this transformer.
     *
     * @param mixed $response
     * @return mixed
     */
    protected function doTransform($response)
    {
        // If response is not an array, return as is
        if (!is_array($response)) {
            return $response;
        }

        // If response is a sequential array, apply mapping to each item
        if ($this->isSequentialArray($response)) {
            return array_map([$this, 'mapItem'], $response);
        }

        // Otherwise, apply mapping to the response itself
        return $this->mapItem($response);
    }

    /**
     * Map the keys of an item.
     *
     * @param array $item
     * @return array
     */
    protected function mapItem(array $item)
    {
        $result = [];

        // Process each key in the item
        foreach ($item as $key => $value) {
            // If key exists in mapping, use the mapped key
            if (isset($this->mapping[$key])) {
                $newKey = $this->mapping[$key];
                $result[$newKey] = $value;
            } elseif (!$this->removeUnmapped) {
                // If not removing unmapped keys, keep the original key
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Check if an array is sequential (numeric keys starting from 0).
     *
     * @param array $array
     * @return bool
     */
    protected function isSequentialArray(array $array)
    {
        if (empty($array)) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Get the name of this transformer.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'key_mapping';
    }
}
