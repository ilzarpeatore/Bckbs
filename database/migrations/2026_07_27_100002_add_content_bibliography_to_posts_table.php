<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->longText('content')->nullable()->after('description');
            $table->longText('bibliography')->nullable()->after('content');
            $table->unsignedBigInteger('blog_category_id')->nullable()->after('category_ids');
            $table->foreign('blog_category_id')->references('id')->on('blog_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['blog_category_id']);
            $table->dropColumn(['content', 'bibliography', 'blog_category_id']);
        });
    }
};
