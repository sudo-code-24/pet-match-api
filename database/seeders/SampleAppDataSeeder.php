<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Pet;
use App\Models\Shelter;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SampleAppDataSeeder extends Seeder
{
    public function run(): void
    {
        $shelterAddress = Address::query()->updateOrCreate(
            ['full_address' => '123 Rescue Way, Minglanilla, Cebu, Philippines'],
            [
                'street' => '123 Rescue Way',
                'barangay' => 'Tungkop',
                'city' => 'Minglanilla',
                'province' => 'Cebu',
                'zip_code' => '6046',
                'country' => 'Philippines',
                'latitude' => 10.2522,
                'longitude' => 123.7952,
            ],
        );

        $shelterUser = User::query()->firstOrCreate(
            ['email' => 'shelter.demo@petmatch.app'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Cebu Rescue Haven',
                'role' => 'shelter',
                'password' => Hash::make('Password@123'),
                'push_notifications_enabled' => true,
            ],
        );
        $shelterUser->fill([
            'name' => 'Cebu Rescue Haven',
            'role' => 'shelter',
            'password' => Hash::make('Password@123'),
            'push_notifications_enabled' => true,
        ]);
        $shelterUser->save();

        UserProfile::query()->updateOrCreate(
            ['user_id' => $shelterUser->id],
            [
                'first_name' => 'Cebu',
                'last_name' => 'Rescue',
                'bio' => 'Community-driven shelter supporting rescue, foster, and adoption.',
                'is_discoverable' => true,
                'address_id' => $shelterAddress->id,
            ],
        );

        Shelter::query()->updateOrCreate(
            ['user_id' => $shelterUser->id],
            [
                'organization_name' => 'Cebu Rescue Haven',
                'website' => 'https://cebu-rescue-haven.example.org',
                'ein_tax_id' => 'PH-CRH-2026-001',
                'physical_address' => '123 Rescue Way, Minglanilla, Cebu, Philippines',
                'bio_mission' => 'We rescue, rehabilitate, and rehome abandoned cats and dogs.',
                'logo' => 'shelter-logos/sample-cebu-rescue-haven.png',
                'verification_docs' => [
                    'shelter-verification-docs/sample-registration.pdf',
                    'shelter-verification-docs/sample-permit.pdf',
                ],
                'shelter_type' => 'Rescue',
                'max_capacity' => 80,
                'facilities' => [
                    'Outdoor Space',
                    'Quarantine Area',
                    'On-site Clinic',
                    'Training Barn',
                ],
                'operating_hours' => [
                    'weekday' => ['open' => '09:00', 'close' => '18:00'],
                    'weekend' => ['open' => '10:00', 'close' => '16:00'],
                ],
                'services_offered' => [
                    'Adoption' => true,
                    'Fostering' => true,
                    'Spay/Neuter' => true,
                    'Microchipping' => false,
                ],
                'adoption_requirements' => 'Valid ID, interview, and optional home check for first-time adopters.',
                // Legacy fields for existing consumers.
                'shelter_name' => 'Cebu Rescue Haven',
                'description' => 'Community-driven shelter supporting rescue, foster, and adoption.',
                'address' => '123 Rescue Way, Minglanilla, Cebu, Philippines',
                'address_id' => $shelterAddress->id,
                'phone' => '+63 32 555 0198',
                'verification_status' => 'pending',
            ],
        );

        $mochi = Pet::query()->withoutGlobalScopes()->firstOrNew([
            'name' => 'Mochi',
            'user_id' => $shelterUser->id,
        ]);
        if (! $mochi->exists) {
            $mochi->id = (string) Str::uuid();
        }
        $mochi->fill([
                'species' => 'dog',
            'gender' => 'female',
                'breed' => 'Mixed',
                'age' => 2,
                'health_notes' => 'Vaccinated and dewormed.',
                'adoption_details' => 'Friendly with children and other dogs.',
                'purpose' => 'adoption',
                'image_url' => '/pets/sample/mochi.jpg',
                'image_urls' => ['/pets/sample/mochi.jpg'],
                'active' => true,
        ]);
        $mochi->save();

        $biscuit = Pet::query()->withoutGlobalScopes()->firstOrNew([
            'name' => 'Biscuit',
            'user_id' => $shelterUser->id,
        ]);
        if (! $biscuit->exists) {
            $biscuit->id = (string) Str::uuid();
        }
        $biscuit->fill([
                'species' => 'cat',
            'gender' => 'male',
                'breed' => 'Domestic Shorthair',
                'age' => 1,
                'health_notes' => 'Neutered and litter-trained.',
                'adoption_details' => 'Best for quiet homes.',
                'purpose' => 'adoption',
                'image_url' => '/pets/sample/biscuit.jpg',
                'image_urls' => ['/pets/sample/biscuit.jpg'],
                'active' => true,
        ]);
        $biscuit->save();

        $fosterAddress = Address::query()->updateOrCreate(
            ['full_address' => '27 Sunshine Street, Cebu City, Philippines'],
            [
                'street' => '27 Sunshine Street',
                'barangay' => 'Guadalupe',
                'city' => 'Cebu City',
                'province' => 'Cebu',
                'zip_code' => '6000',
                'country' => 'Philippines',
                'latitude' => 10.3157,
                'longitude' => 123.8854,
            ],
        );

        $fosterUser = User::query()->firstOrCreate(
            ['email' => 'foster.demo@petmatch.app'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Jamie Foster',
                'role' => 'foster',
                'password' => Hash::make('Password@123'),
                'push_notifications_enabled' => true,
            ],
        );
        $fosterUser->fill([
            'name' => 'Jamie Foster',
            'role' => 'foster',
            'password' => Hash::make('Password@123'),
            'push_notifications_enabled' => true,
        ]);
        $fosterUser->save();

        UserProfile::query()->updateOrCreate(
            ['user_id' => $fosterUser->id],
            [
                'first_name' => 'Jamie',
                'last_name' => 'Foster',
                'bio' => 'Pet parent and volunteer adopter screener.',
                'is_discoverable' => true,
                'address_id' => $fosterAddress->id,
            ],
        );
    }
}

