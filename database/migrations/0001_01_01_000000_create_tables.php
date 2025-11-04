<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // ---- Users ----
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('timezone')->default('America/Argentina/Buenos_Aires');
            $table->string('currency_code', 3)->default('ARS'); // base reporting currency
            $table->string('locale', 8)->default('es-AR');
            $table->enum('role', ['user','admin'])->default('user');

            // Subscription cache
            $table->boolean('is_premium')->default(false);
            $table->timestamp('premium_since')->nullable();
            $table->timestamp('trial_ends_at')->nullable();

            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['currency_code']);
            $table->index(['is_premium']);
            $table->index(['trial_ends_at']);
        });

        // ---- Plans ----
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                 // premium_monthly, premium_annual
            $table->string('name');
            $table->enum('interval', ['monthly','annual']);
            $table->unsignedBigInteger('price_cents');        // price in plan currency
            $table->string('currency_code', 3)->default('USD');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // ---- Subscriptions (Mercado Pago preapproval) ----
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();

            $table->enum('provider', ['mercadopago'])->default('mercadopago');
            // MP recurring: preapproval_id
            $table->string('provider_preapproval_id')->nullable();

            $table->enum('status', ['trialing','active','past_due','canceled','incomplete'])->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id','status']);
            $table->index(['provider','provider_preapproval_id']);
        });

        // ---- Subscription payments (audit) ----
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->enum('provider', ['mercadopago'])->default('mercadopago');
            $table->string('provider_payment_id')->nullable(); // MP payment id
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency_code', 3)->default('ARS');
            $table->enum('status', ['paid','refunded','failed','pending'])->default('paid');
            $table->timestamp('paid_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['status','paid_at']);
        });

        // ---- Features payments (limit users) ----
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();                  // e.g. whatsapp_logging, advanced_stats, exports
            $table->enum('kind', ['binary','quota'])->default('binary');
            $table->unsignedInteger('default_quota')->nullable();  // only for 'quota'
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // ---- Plan features N:N ----
        Schema::create('plan_features', function (Blueprint $table) {
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('quota_override')->nullable(); // for 'quota'
            $table->timestamps();

            $table->primary(['plan_id','feature_id']);
            $table->index(['feature_id']);
        });

        // ---- Limit quota for a feature that needs it ----
        Schema::create('feature_usage', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->char('period_ym', 7);                         // "2025-11"
            $table->unsignedInteger('used')->default(0);
            $table->timestamps();
            $table->primary(['user_id','feature_id','period_ym']);
            $table->index(['feature_id','period_ym']);
        });

        // ---- Payment methods ----
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['cash','credit_card','debit_card','bank_transfer','digital_wallet','other'])->default('cash');
            $table->string('label')->nullable();    // "Visa", "Ualá", etc.
            $table->string('issuer')->nullable();   // bank/issuer
            $table->string('network')->nullable();  // Visa/Master/Amex
            $table->string('last4', 4)->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id','type','is_default']);
        });

        // ---- Accounts (simple; for transfers & balances) ----
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('color', 9)->nullable();           // #RRGGBB
            $table->string('currency_code', 3)->default('ARS'); // future-proof
            $table->boolean('is_default')->default(false);
            $table->boolean('archived')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();             // optional extras
            $table->timestamps();

            $table->unique(['user_id','label']);
            $table->index(['user_id','archived','is_default']);
        });

        // ---- Categories (create WITHOUT foreign key first) ----
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete(); // null => system
            $table->string('name');
            $table->enum('kind', ['expense', 'income', 'both'])->default('expense');
            $table->string('color', 9)->nullable();   // #RRGGBB
            $table->string('icon')->nullable();
            $table->boolean('is_system')->default(false);
            $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();

            // Add default_account_id column but WITHOUT foreign key constraint yet
            $table->unsignedBigInteger('default_account_id')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'name', 'kind']);
            $table->index(['is_system', 'kind']);
            $table->index(['default_account_id']); // helpful for lookups
        });

        // ---- Exchange rates (future) ----
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_code', 3);
            $table->string('quote_code', 3);
            $table->decimal('rate', 18, 8);   // quote per 1 base
            $table->date('as_of_date');
            $table->timestamps();

            $table->unique(['base_code','quote_code','as_of_date']);
        });

        // ---- Transactions (expense/income/transfer) ----
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['expense','income','transfer'])->index();

            $table->unsignedBigInteger('amount_cents');        // always positive
            $table->string('currency_code', 3)->default('ARS'); // original currency only
            $table->timestamp('occurred_at');                  // UTC

            $table->string('description')->nullable();
            $table->string('merchant')->nullable();
            $table->text('notes')->nullable();

            // Non-transfer: the single account impacted (e.g., cash/card)
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();

            // Transfer-only fields: move funds between accounts (same currency for now)
            $table->foreignId('source_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('target_account_id')->nullable()->constrained('accounts')->nullOnDelete();

            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();

            $table->boolean('is_recurring')->default(false);
            $table->uuid('recurrence_group_id')->nullable();

            $table->json('tags')->nullable();                   // ["work","trip"]

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id','occurred_at']);
            $table->index(['category_id']);
            $table->index(['payment_method_id']);
            $table->index(['account_id']);
            $table->index(['source_account_id','target_account_id']);
        });

        // NOW add the foreign key constraint for categories.default_account_id
        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('default_account_id')
                ->references('id')
                ->on('accounts')
                ->nullOnDelete();
        });

        // (Optional) lightweight CHECKs for MySQL 8+ (ignored on older versions)
        try {
            DB::statement("
                ALTER TABLE transactions
                ADD CONSTRAINT chk_txn_accounts
                CHECK (
                    (type IN ('expense','income') AND account_id IS NOT NULL AND source_account_id IS NULL AND target_account_id IS NULL)
                    OR
                    (type = 'transfer' AND account_id IS NULL AND source_account_id IS NOT NULL AND target_account_id IS NOT NULL AND source_account_id <> target_account_id)
                )
            ");
        } catch (\Throwable $e) {
            // Skip if CHECK not supported
        }

        // ---- WhatsApp messages (full payloads) ----
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('wa_message_id')->nullable();
            $table->string('phone')->nullable();
            $table->enum('direction', ['inbound','outbound'])->index();
            $table->enum('status', ['received','processed','failed','queued','sent','delivered'])->default('received')->index();
            $table->string('message_type')->default('text');
            $table->longText('body')->nullable();
            $table->json('raw_payload')->nullable();    // store raw inbound/outbound payloads
            $table->json('parsed_json')->nullable();    // parser extraction result
            $table->enum('parse_status', ['success','failed','skipped'])->nullable()->index();

            $table->foreignId('related_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            $table->timestamp('received_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index(['wa_message_id']);
            $table->index(['user_id','received_at']);
        });

        // ---- Daily summaries (20:00 local if there were messages) ----
        Schema::create('daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('summary_date'); // local date in user's TZ
            $table->unsignedInteger('transactions_count')->default(0);
            $table->unsignedBigInteger('total_expense_cents')->default(0);
            $table->unsignedBigInteger('total_income_cents')->default(0);
            $table->string('currency_code', 3)->default('ARS');

            $table->enum('channel', ['whatsapp','email'])->default('whatsapp');
            $table->string('template_name')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id','summary_date','channel']);
            $table->index(['sent_at']);
        });

        // ---- Budgets ----
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete(); // null => global
            $table->string('name');
            $table->enum('period', ['weekly','monthly','quarterly','yearly','custom'])->default('monthly');
            $table->date('period_start_date');
            $table->date('period_end_date');
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency_code', 3)->default('ARS');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['user_id','period_start_date','period_end_date']);
        });

        // ---- Milestones (optional analytics candy) ----
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code');      // e.g., "first_10_logs"
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('achieved_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id','code']);
        });

        // ---- Webhook events (MP / WhatsApp) ----
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('source');                    // mercadopago | whatsapp
            $table->string('event_type')->nullable();
            $table->string('external_id')->nullable();
            $table->json('payload');                     // full JSON
            $table->enum('status', ['received','processed','failed'])->default('received')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['source','event_type']);
            $table->index(['external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('daily_summaries');
        Schema::dropIfExists('whatsapp_messages');     
        Schema::dropIfExists('categories');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('feature_usage');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('features');
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('users');
    }
};