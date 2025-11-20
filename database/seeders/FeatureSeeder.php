<?php

namespace Database\Seeders;

use App\Models\Features\Feature;
use App\Models\Subscriptions\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedFeatures();
            $this->seedPlans();
            $this->seedPlanFeatures();
        });

        $this->command->info('✅ Features and plan mappings seeded successfully!');
    }

    /**
     * Seed features table
     */
    private function seedFeatures(): void
    {
        $features = [
            [
                'slug' => 'whatsapp_logging',
                'kind' => 'binary',
                'default_quota' => null,
                'description' => 'Registro vía WhatsApp',
            ],
            [
                'slug' => 'budgeting_system',
                'kind' => 'binary',
                'default_quota' => null,
                'description' => 'Sistema de Presupuestos',
            ],
            [
                'slug' => 'alerts_system',
                'kind' => 'binary',
                'default_quota' => null,
                'description' => 'Sistema de Alertas',
            ],
            [
                'slug' => 'exports',
                'kind' => 'quota',
                'default_quota' => 2, // Default for free plan
                'description' => 'Exportaciones CSV/PDF',
            ],
            [
                'slug' => 'exchange_rates',
                'kind' => 'binary',
                'default_quota' => null,
                'description' => 'Conversión de Divisas',
            ],
            [
                'slug' => 'payment_insights',
                'kind' => 'binary',
                'default_quota' => null,
                'description' => 'Análisis de Métodos de Pago',
            ],
            [
                'slug' => 'ai_categorization',
                'kind' => 'binary',
                'default_quota' => null,
                'description' => 'Categorización con IA',
            ],
            [
                'slug' => 'daily_summaries',
                'kind' => 'binary',
                'default_quota' => null,
                'description' => 'Resúmenes Diarios',
            ],
            [
                'slug' => 'milestone_tracking',
                'kind' => 'binary',
                'default_quota' => null,
                'description' => 'Seguimiento de Hitos',
            ],
            [
                'slug' => 'advanced_analytics',
                'kind' => 'binary',
                'default_quota' => null,
                'description' => 'Análisis Avanzado',
            ],
        ];

        foreach ($features as $featureData) {
            Feature::updateOrCreate(
                ['slug' => $featureData['slug']],
                $featureData
            );
        }

        $this->command->info('   → Features seeded');
    }

    /**
     * Seed plans table if needed
     */
    private function seedPlans(): void
    {
        $plans = [
            [
                'code' => 'free',
                'name' => 'Gratis',
                'interval' => 'monthly',
                'price_cents' => 0,
                'currency_code' => 'ARS',
                'active' => true,
            ],
            [
                'code' => 'premium_monthly',
                'name' => 'Premium Mensual',
                'interval' => 'monthly',
                'price_cents' => 999900, // 9999 ARS
                'currency_code' => 'ARS',
                'active' => true,
            ],
            [
                'code' => 'premium_annual',
                'name' => 'Premium Anual',
                'interval' => 'annual',
                'price_cents' => 9999900, // 99999 ARS (2 meses gratis)
                'currency_code' => 'ARS',
                'active' => true,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['code' => $planData['code']],
                $planData
            );
        }

        $this->command->info('   → Plans seeded');
    }

    /**
     * Seed plan_features pivot table
     */
    private function seedPlanFeatures(): void
    {
        $freePlan = Plan::where('code', 'free')->first();
        $premiumMonthly = Plan::where('code', 'premium_monthly')->first();
        $premiumAnnual = Plan::where('code', 'premium_annual')->first();

        // Free Plan Features
        $this->attachFeaturesToPlan($freePlan, [
            'exports' => ['enabled' => true, 'quota_override' => 2],
            'milestone_tracking' => ['enabled' => true, 'quota_override' => null],
        ]);

        // Premium Monthly Features (all features enabled)
        $allFeatures = Feature::all();
        $premiumFeatures = [];

        foreach ($allFeatures as $feature) {
            $premiumFeatures[$feature->slug] = [
                'enabled' => true,
                'quota_override' => $feature->kind === 'quota' ? null : null, // null = unlimited
            ];
        }

        $this->attachFeaturesToPlan($premiumMonthly, $premiumFeatures);

        // Premium Annual (same as monthly)
        $this->attachFeaturesToPlan($premiumAnnual, $premiumFeatures);

        $this->command->info('   → Plan features mapped');
    }

    /**
     * Attach features to a plan
     */
    private function attachFeaturesToPlan(Plan $plan, array $features): void
    {
        foreach ($features as $featureSlug => $config) {
            $feature = Feature::where('slug', $featureSlug)->first();

            if (!$feature) {
                $this->command->warn("   ⚠️  Feature not found: {$featureSlug}");
                continue;
            }

            // Check if already attached
            $existing = DB::table('plan_features')
                ->where('plan_id', $plan->id)
                ->where('feature_id', $feature->id)
                ->first();

            if ($existing) {
                // Update
                DB::table('plan_features')
                    ->where('plan_id', $plan->id)
                    ->where('feature_id', $feature->id)
                    ->update([
                        'enabled' => $config['enabled'],
                        'quota_override' => $config['quota_override'],
                        'updated_at' => now(),
                    ]);
            } else {
                // Insert
                DB::table('plan_features')->insert([
                    'plan_id' => $plan->id,
                    'feature_id' => $feature->id,
                    'enabled' => $config['enabled'],
                    'quota_override' => $config['quota_override'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}