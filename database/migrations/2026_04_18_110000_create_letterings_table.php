<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('is_lettrable')->default(false)->after('is_active');
        });

        Schema::create('letterings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('code', 20); // e.g. "L0001" - unique per account
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('match_type', 20)->default('manual'); // manual|auto_reference|auto_amount
            $table->text('notes')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['account_id', 'code']);
            $table->index(['company_id', 'account_id', 'contact_id']);
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->foreignUuid('lettering_id')
                ->nullable()
                ->after('contact_id')
                ->constrained('letterings')
                ->nullOnDelete();

            $table->index(['account_id', 'lettering_id']);
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropForeign(['lettering_id']);
            $table->dropIndex(['account_id', 'lettering_id']);
            $table->dropColumn('lettering_id');
        });

        Schema::dropIfExists('letterings');

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('is_lettrable');
        });
    }
};
