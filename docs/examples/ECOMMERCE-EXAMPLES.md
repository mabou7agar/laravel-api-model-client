# E-commerce API Integration Examples

This guide provides comprehensive examples for integrating with popular e-commerce APIs using the Laravel API Model Client with OpenAPI support.

## Table of Contents

1. [Bagisto E-commerce Integration](#bagisto-ecommerce-integration)
2. [WooCommerce REST API](#woocommerce-rest-api)
3. [Shopify Admin API](#shopify-admin-api)
4. [Magento 2 REST API](#magento-2-rest-api)
5. [Custom E-commerce API](#custom-ecommerce-api)
6. [Multi-Store Management](#multi-store-management)
7. [Inventory Synchronization](#inventory-synchronization)
8. [Order Processing Workflows](#order-processing-workflows)

## Bagisto E-commerce Integration

### Configuration

First, configure Bagisto API in your `config/api-client.php`:

```php
<?php

return [
    'schemas' => [
        'bagisto' => [
            'source' => env('BAGISTO_OPENAPI_SCHEMA', 'https://your-bagisto.com/api/docs/openapi.json'),
            'base_url' => env('BAGISTO_BASE_URL', 'https://your-bagisto.com/api'),
            'authentication' => [
                'type' => 'bearer',
                'token' => env('BAGISTO_API_TOKEN'),
            ],
            'validation' => [
                'strictness' => 'moderate',
            ],
            'caching' => [
                'enabled' => true,
                'ttl' => 1800, // 30 minutes
            ],
        ],
    ],
];
```

### Environment Variables

```env
BAGISTO_OPENAPI_SCHEMA=https://your-bagisto.com/api/docs/openapi.json
BAGISTO_BASE_URL=https://your-bagisto.com/api
BAGISTO_API_TOKEN=your-bagisto-api-token
```

### Bagisto Product Model

```php
<?php

namespace App\Models\Bagisto;

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Traits\HasOpenApiSchema;
use Illuminate\Support\Str;

/**
 * Bagisto Product Model
 * 
 * @property int $id
 * @property string $sku
 * @property string $name
 * @property string $description
 * @property float $price
 * @property int $quantity
 * @property string $status
 * @property int $category_id
 * @property array $images
 * @property array $attributes
 */
class Product extends ApiModel
{
    use HasOpenApiSchema;

    protected string $openApiSchemaSource = 'bagisto';
    protected string $endpoint = '/products';
    protected string $primaryKey = 'id';

    // Bagisto-specific configuration
    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . config('api-client.schemas.bagisto.authentication.token'),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }

    // Transform Laravel naming to Bagisto API naming
    protected function transformParametersForApi(array $parameters): array
    {
        $transformed = [];
        
        foreach ($parameters as $key => $value) {
            // Convert snake_case to camelCase for Bagisto
            $apiKey = Str::camel($key);
            $transformed[$apiKey] = $value;
        }
        
        // Handle special Bagisto fields
        if (isset($transformed['categoryId'])) {
            $transformed['categories'] = [$transformed['categoryId']];
            unset($transformed['categoryId']);
        }
        
        return $transformed;
    }

    // Transform Bagisto API response to Laravel naming
    protected function transformResponseFromApi(array $response): array
    {
        $transformed = [];
        
        foreach ($response as $key => $value) {
            // Convert camelCase to snake_case
            $modelKey = Str::snake($key);
            $transformed[$modelKey] = $value;
        }
        
        // Handle Bagisto-specific response structure
        if (isset($transformed['categories']) && is_array($transformed['categories'])) {
            $transformed['category_id'] = $transformed['categories'][0]['id'] ?? null;
        }
        
        return $transformed;
    }

    // Bagisto-specific scopes
    public function scopeInStock($query)
    {
        return $query->whereOpenApi('quantity', '>', 0);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->whereOpenApi('category_id', $categoryId);
    }

    public function scopeActive($query)
    {
        return $query->whereOpenApi('status', 'active');
    }

    public function scopeOnSale($query)
    {
        return $query->whereOpenApi('special_price', '!=', null)
                    ->whereOpenApi('special_price_from', '<=', now())
                    ->whereOpenApi('special_price_to', '>=', now());
    }

    // Bagisto-specific methods
    public function updateInventory(int $quantity): bool
    {
        return $this->update(['quantity' => $quantity]);
    }

    public function addToCategory(int $categoryId): bool
    {
        $categories = $this->categories ?? [];
        if (!in_array($categoryId, array_column($categories, 'id'))) {
            $categories[] = ['id' => $categoryId];
            return $this->update(['categories' => $categories]);
        }
        return true;
    }

    public function setSpecialPrice(float $price, $from = null, $to = null): bool
    {
        return $this->update([
            'special_price' => $price,
            'special_price_from' => $from ?? now(),
            'special_price_to' => $to ?? now()->addMonth(),
        ]);
    }

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class, 'parent_id');
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class, 'product_id');
    }
}
```

### Bagisto Category Model

```php
<?php

namespace App\Models\Bagisto;

class Category extends ApiModel
{
    use HasOpenApiSchema;

    protected string $openApiSchemaSource = 'bagisto';
    protected string $endpoint = '/categories';

    protected function transformParametersForApi(array $parameters): array
    {
        $transformed = parent::transformParametersForApi($parameters);
        
        // Handle Bagisto category hierarchy
        if (isset($transformed['parent_id']) && $transformed['parent_id'] === 0) {
            unset($transformed['parent_id']);
        }
        
        return $transformed;
    }

    // Category-specific scopes
    public function scopeRootCategories($query)
    {
        return $query->whereOpenApi('parent_id', null);
    }

    public function scopeActive($query)
    {
        return $query->whereOpenApi('status', 1);
    }

    // Relationships
    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // Category tree methods
    public function getDescendants()
    {
        $descendants = collect();
        
        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getDescendants());
        }
        
        return $descendants;
    }

    public function getBreadcrumb()
    {
        $breadcrumb = collect([$this]);
        
        $parent = $this->parent;
        while ($parent) {
            $breadcrumb->prepend($parent);
            $parent = $parent->parent;
        }
        
        return $breadcrumb;
    }
}
```

### Bagisto Order Management

```php
<?php

namespace App\Models\Bagisto;

class Order extends ApiModel
{
    use HasOpenApiSchema;

    protected string $openApiSchemaSource = 'bagisto';
    protected string $endpoint = '/orders';

    protected $casts = [
        'order_date' => 'datetime',
        'items' => 'array',
        'billing_address' => 'array',
        'shipping_address' => 'array',
    ];

    // Order status scopes
    public function scopePending($query)
    {
        return $query->whereOpenApi('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->whereOpenApi('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->whereOpenApi('status', 'completed');
    }

    public function scopeCanceled($query)
    {
        return $query->whereOpenApi('status', 'canceled');
    }

    // Order management methods
    public function markAsProcessing(): bool
    {
        return $this->update(['status' => 'processing']);
    }

    public function markAsShipped(string $trackingNumber = null): bool
    {
        $data = ['status' => 'shipped'];
        if ($trackingNumber) {
            $data['tracking_number'] = $trackingNumber;
        }
        return $this->update($data);
    }

    public function markAsDelivered(): bool
    {
        return $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function cancel(string $reason = null): bool
    {
        return $this->update([
            'status' => 'canceled',
            'canceled_at' => now(),
            'cancel_reason' => $reason,
        ]);
    }

    // Calculate totals
    public function calculateSubtotal(): float
    {
        return collect($this->items)->sum(function ($item) {
            return $item['quantity'] * $item['price'];
        });
    }

    public function calculateTax(): float
    {
        return $this->calculateSubtotal() * ($this->tax_rate / 100);
    }

    public function calculateTotal(): float
    {
        return $this->calculateSubtotal() + $this->calculateTax() + $this->shipping_cost;
    }

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }
}
```

## WooCommerce REST API

### Configuration

```php
// config/api-client.php
'schemas' => [
    'woocommerce' => [
        'source' => env('WOOCOMMERCE_OPENAPI_SCHEMA'),
        'base_url' => env('WOOCOMMERCE_BASE_URL') . '/wp-json/wc/v3',
        'authentication' => [
            'type' => 'basic',
            'username' => env('WOOCOMMERCE_CONSUMER_KEY'),
            'password' => env('WOOCOMMERCE_CONSUMER_SECRET'),
        ],
        'validation' => [
            'strictness' => 'lenient', // WooCommerce can be flexible
        ],
    ],
],
```

### WooCommerce Product Model

```php
<?php

namespace App\Models\WooCommerce;

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Traits\HasOpenApiSchema;

class Product extends ApiModel
{
    use HasOpenApiSchema;

    protected string $openApiSchemaSource = 'woocommerce';
    protected string $endpoint = '/products';

    protected $casts = [
        'date_created' => 'datetime',
        'date_modified' => 'datetime',
        'categories' => 'array',
        'images' => 'array',
        'attributes' => 'array',
        'meta_data' => 'array',
    ];

    // WooCommerce authentication
    protected function getHeaders(): array
    {
        $credentials = base64_encode(
            config('api-client.schemas.woocommerce.authentication.username') . ':' .
            config('api-client.schemas.woocommerce.authentication.password')
        );

        return [
            'Authorization' => 'Basic ' . $credentials,
            'Content-Type' => 'application/json',
        ];
    }

    // WooCommerce-specific scopes
    public function scopePublished($query)
    {
        return $query->whereOpenApi('status', 'publish');
    }

    public function scopeInStock($query)
    {
        return $query->whereOpenApi('stock_status', 'instock');
    }

    public function scopeOnSale($query)
    {
        return $query->whereOpenApi('on_sale', true);
    }

    public function scopeFeatured($query)
    {
        return $query->whereOpenApi('featured', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->whereOpenApi('category', $categoryId);
    }

    // WooCommerce-specific methods
    public function updateStock(int $quantity): bool
    {
        return $this->update([
            'stock_quantity' => $quantity,
            'stock_status' => $quantity > 0 ? 'instock' : 'outofstock',
        ]);
    }

    public function setSalePrice(float $salePrice): bool
    {
        return $this->update([
            'sale_price' => $salePrice,
            'on_sale' => true,
        ]);
    }

    public function removeSale(): bool
    {
        return $this->update([
            'sale_price' => '',
            'on_sale' => false,
        ]);
    }

    public function addCategory(int $categoryId): bool
    {
        $categories = $this->categories ?? [];
        $categoryIds = array_column($categories, 'id');
        
        if (!in_array($categoryId, $categoryIds)) {
            $categories[] = ['id' => $categoryId];
            return $this->update(['categories' => $categories]);
        }
        
        return true;
    }

    public function addImage(string $src, string $alt = ''): bool
    {
        $images = $this->images ?? [];
        $images[] = [
            'src' => $src,
            'alt' => $alt,
        ];
        
        return $this->update(['images' => $images]);
    }

    // Get formatted price
    public function getFormattedPrice(): string
    {
        $price = $this->on_sale ? $this->sale_price : $this->regular_price;
        return number_format($price, 2);
    }

    // Check if product has variations
    public function hasVariations(): bool
    {
        return $this->type === 'variable';
    }

    // Relationships
    public function variations()
    {
        return $this->hasMany(ProductVariation::class, 'parent_id');
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class, 'product_id');
    }
}
```

### WooCommerce Order Processing

```php
<?php

namespace App\Models\WooCommerce;

class Order extends ApiModel
{
    use HasOpenApiSchema;

    protected string $openApiSchemaSource = 'woocommerce';
    protected string $endpoint = '/orders';

    protected $casts = [
        'date_created' => 'datetime',
        'date_modified' => 'datetime',
        'line_items' => 'array',
        'billing' => 'array',
        'shipping' => 'array',
        'meta_data' => 'array',
    ];

    // Order status scopes
    public function scopePending($query)
    {
        return $query->whereOpenApi('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->whereOpenApi('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->whereOpenApi('status', 'completed');
    }

    // Order management
    public function updateStatus(string $status, string $note = ''): bool
    {
        $data = ['status' => $status];
        
        if ($note) {
            $data['customer_note'] = $note;
        }
        
        return $this->update($data);
    }

    public function addNote(string $note, bool $customerNote = false): bool
    {
        return $this->apiCall('POST', "/orders/{$this->id}/notes", [
            'note' => $note,
            'customer_note' => $customerNote,
        ]);
    }

    public function refund(float $amount, string $reason = ''): array
    {
        return $this->apiCall('POST', '/refunds', [
            'order_id' => $this->id,
            'amount' => $amount,
            'reason' => $reason,
        ]);
    }

    // Calculate order totals
    public function getSubtotal(): float
    {
        return collect($this->line_items)->sum(function ($item) {
            return $item['quantity'] * $item['price'];
        });
    }

    public function getTaxTotal(): float
    {
        return (float) $this->total_tax;
    }

    public function getShippingTotal(): float
    {
        return (float) $this->shipping_total;
    }

    // Customer information
    public function getCustomerName(): string
    {
        $billing = $this->billing;
        return trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));
    }

    public function getCustomerEmail(): string
    {
        return $this->billing['email'] ?? '';
    }

    public function getShippingAddress(): string
    {
        $shipping = $this->shipping;
        return implode(', ', array_filter([
            $shipping['address_1'] ?? '',
            $shipping['address_2'] ?? '',
            $shipping['city'] ?? '',
            $shipping['state'] ?? '',
            $shipping['postcode'] ?? '',
            $shipping['country'] ?? '',
        ]));
    }
}
```

## Shopify Admin API

### Configuration

```php
// config/api-client.php
'schemas' => [
    'shopify' => [
        'source' => 'https://shopify.dev/docs/admin-api/rest/reference/openapi.json',
        'base_url' => 'https://' . env('SHOPIFY_SHOP_DOMAIN') . '.myshopify.com/admin/api/2023-10',
        'authentication' => [
            'type' => 'custom',
            'headers' => [
                'X-Shopify-Access-Token' => env('SHOPIFY_ACCESS_TOKEN'),
            ],
        ],
        'validation' => [
            'strictness' => 'strict',
        ],
    ],
],
```

### Shopify Product Model

```php
<?php

namespace App\Models\Shopify;

use MTechStack\LaravelApiModelClient\Models\ApiModel;
use MTechStack\LaravelApiModelClient\Traits\HasOpenApiSchema;

class Product extends ApiModel
{
    use HasOpenApiSchema;

    protected string $openApiSchemaSource = 'shopify';
    protected string $endpoint = '/products';

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'published_at' => 'datetime',
        'variants' => 'array',
        'images' => 'array',
        'options' => 'array',
        'tags' => 'string', // Shopify uses comma-separated string
    ];

    // Shopify authentication
    protected function getHeaders(): array
    {
        return [
            'X-Shopify-Access-Token' => config('api-client.schemas.shopify.authentication.headers.X-Shopify-Access-Token'),
            'Content-Type' => 'application/json',
        ];
    }

    // Handle Shopify's nested response structure
    protected function transformResponseFromApi(array $response): array
    {
        // Shopify wraps single resources in a type key
        if (isset($response['product'])) {
            return $response['product'];
        }
        
        return $response;
    }

    protected function transformParametersForApi(array $parameters): array
    {
        // Shopify expects data wrapped in a type key for creation/updates
        return ['product' => parent::transformParametersForApi($parameters)];
    }

    // Shopify-specific scopes
    public function scopePublished($query)
    {
        return $query->whereOpenApi('published_status', 'published');
    }

    public function scopeDraft($query)
    {
        return $query->whereOpenApi('published_status', 'unpublished');
    }

    public function scopeByVendor($query, string $vendor)
    {
        return $query->whereOpenApi('vendor', $vendor);
    }

    public function scopeByProductType($query, string $productType)
    {
        return $query->whereOpenApi('product_type', $productType);
    }

    public function scopeWithTag($query, string $tag)
    {
        return $query->whereOpenApi('tag', $tag);
    }

    // Shopify-specific methods
    public function publish(): bool
    {
        return $this->update([
            'published_status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function unpublish(): bool
    {
        return $this->update([
            'published_status' => 'unpublished',
            'published_at' => null,
        ]);
    }

    public function addToCollection(int $collectionId): bool
    {
        return $this->apiCall('POST', "/collections/{$collectionId}/products", [
            'product_id' => $this->id,
        ]);
    }

    public function removeFromCollection(int $collectionId): bool
    {
        return $this->apiCall('DELETE', "/collections/{$collectionId}/products/{$this->id}");
    }

    public function addTag(string $tag): bool
    {
        $tags = $this->getTagsArray();
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            return $this->update(['tags' => implode(',', $tags)]);
        }
        return true;
    }

    public function removeTag(string $tag): bool
    {
        $tags = $this->getTagsArray();
        $tags = array_filter($tags, fn($t) => $t !== $tag);
        return $this->update(['tags' => implode(',', $tags)]);
    }

    public function getTagsArray(): array
    {
        return array_filter(array_map('trim', explode(',', $this->tags ?? '')));
    }

    // Inventory management
    public function updateInventory(int $variantId, int $quantity): bool
    {
        return $this->apiCall('POST', "/inventory_levels/set", [
            'inventory_item_id' => $this->getInventoryItemId($variantId),
            'available' => $quantity,
            'location_id' => config('shopify.primary_location_id'),
        ]);
    }

    private function getInventoryItemId(int $variantId): int
    {
        $variant = collect($this->variants)->firstWhere('id', $variantId);
        return $variant['inventory_item_id'] ?? 0;
    }

    // Relationships
    public function variants()
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    public function collections()
    {
        return $this->belongsToMany(Collection::class, null, 'product_id', 'collection_id');
    }
}
```

### Shopify Order Fulfillment

```php
<?php

namespace App\Models\Shopify;

class Order extends ApiModel
{
    use HasOpenApiSchema;

    protected string $openApiSchemaSource = 'shopify';
    protected string $endpoint = '/orders';

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'processed_at' => 'datetime',
        'line_items' => 'array',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'fulfillments' => 'array',
    ];

    // Shopify order scopes
    public function scopeOpen($query)
    {
        return $query->whereOpenApi('financial_status', 'paid')
                    ->whereOpenApi('fulfillment_status', null);
    }

    public function scopeFulfilled($query)
    {
        return $query->whereOpenApi('fulfillment_status', 'fulfilled');
    }

    public function scopePartiallyFulfilled($query)
    {
        return $query->whereOpenApi('fulfillment_status', 'partial');
    }

    public function scopeUnfulfilled($query)
    {
        return $query->whereOpenApi('fulfillment_status', null);
    }

    // Order fulfillment
    public function fulfill(array $lineItems = null, string $trackingNumber = null): array
    {
        $fulfillmentData = [
            'location_id' => config('shopify.primary_location_id'),
            'tracking_number' => $trackingNumber,
            'notify_customer' => true,
        ];

        if ($lineItems) {
            $fulfillmentData['line_items'] = $lineItems;
        }

        return $this->apiCall('POST', "/orders/{$this->id}/fulfillments", [
            'fulfillment' => $fulfillmentData,
        ]);
    }

    public function cancel(string $reason = 'other'): bool
    {
        return $this->apiCall('POST', "/orders/{$this->id}/cancel", [
            'reason' => $reason,
        ]);
    }

    public function refund(float $amount, array $lineItems = []): array
    {
        return $this->apiCall('POST', "/orders/{$this->id}/refunds", [
            'refund' => [
                'amount' => $amount,
                'refund_line_items' => $lineItems,
                'notify' => true,
            ],
        ]);
    }

    // Order calculations
    public function getSubtotalPrice(): float
    {
        return (float) $this->subtotal_price;
    }

    public function getTotalTax(): float
    {
        return (float) $this->total_tax;
    }

    public function getTotalPrice(): float
    {
        return (float) $this->total_price;
    }

    // Customer information
    public function getCustomer(): ?array
    {
        return $this->customer;
    }

    public function getCustomerEmail(): string
    {
        return $this->email ?? $this->customer['email'] ?? '';
    }

    // Risk assessment
    public function isHighRisk(): bool
    {
        // Check if order has high risk indicators
        $riskLevel = $this->getRiskLevel();
        return in_array($riskLevel, ['high', 'medium']);
    }

    public function getRiskLevel(): string
    {
        // Simplified risk assessment
        if ($this->total_price > 1000) return 'high';
        if ($this->financial_status !== 'paid') return 'medium';
        return 'low';
    }
}
```

This comprehensive e-commerce integration guide provides practical examples for the most popular e-commerce platforms. The next sections will cover multi-store management, inventory synchronization, and order processing workflows.
