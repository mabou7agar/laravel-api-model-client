<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('api_cache', function (Blueprint $table) {
            $table->id();
            
            // Polymorphic relationship fields
            $table->string('cacheable_type'); // Model class name (e.g., 'Product', 'Category', 'Order')
            $table->unsignedBigInteger('cacheable_id'); // API entity ID
            
            // Cache metadata
            $table->string('api_endpoint')->nullable(); // API endpoint used
            $table->string('cache_key')->nullable(); // Custom cache key for complex queries
            $table->timestamp('api_synced_at')->nullable(); // Last successful sync
            $table->timestamp('expires_at')->nullable(); // Cache expiration time
            
            // JSON data storage - stores everything as flexible JSON
            $table->longText('api_data'); // Complete API response as JSON
            $table->json('metadata')->nullable(); // Additional metadata (TTL, tags, etc.)
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['cacheable_type', 'cacheable_id'], 'cacheable_index');
            $table->index('api_endpoint');
            $table->index('cache_key');
            $table->index('api_synced_at');
            $table->index('expires_at');
            
            // Unique constraint for polymorphic relationship
            $table->unique(['cacheable_type', 'cacheable_id'], 'unique_cacheable');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('api_cache');
    }
};
