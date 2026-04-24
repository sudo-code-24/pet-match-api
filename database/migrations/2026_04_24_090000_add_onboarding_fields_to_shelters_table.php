<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shelters', function (Blueprint $table): void {
            $table->string('organization_name')->nullable()->after('user_id');
            $table->string('website')->nullable()->after('organization_name');
            $table->string('ein_tax_id')->nullable()->after('website');
            $table->text('physical_address')->nullable()->after('ein_tax_id');
            $table->text('bio_mission')->nullable()->after('physical_address');
            $table->string('logo')->nullable()->after('bio_mission');
            $table->json('verification_docs')->nullable()->after('logo');
            $table->enum('shelter_type', ['Rescue', 'Municipal', 'Sanctuary'])->nullable()->after('verification_docs');
            $table->integer('max_capacity')->nullable()->after('shelter_type');
            $table->json('facilities')->nullable()->after('max_capacity');
            $table->json('operating_hours')->nullable()->after('facilities');
            $table->json('services_offered')->nullable()->after('operating_hours');
            $table->text('adoption_requirements')->nullable()->after('services_offered');
        });
    }

    public function down(): void
    {
        Schema::table('shelters', function (Blueprint $table): void {
            $table->dropColumn([
                'organization_name',
                'website',
                'ein_tax_id',
                'physical_address',
                'bio_mission',
                'logo',
                'verification_docs',
                'shelter_type',
                'max_capacity',
                'facilities',
                'operating_hours',
                'services_offered',
                'adoption_requirements',
            ]);
        });
    }
};

