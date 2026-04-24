<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Shelter;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class ShelterOnboardingService
{
    /**
     * @param  array<string, mixed>  $validated
     * @param  array<int, UploadedFile>  $verificationDocs
     */
    public function upsertForUser(
        User $user,
        array $validated,
        ?UploadedFile $logo,
        array $verificationDocs,
    ): Shelter {
        $shelter = Shelter::query()->firstOrNew(['user_id' => $user->id]);

        $facilities = $this->decodeJsonField($validated['facilities'] ?? null, []);
        $operatingHours = $this->decodeJsonField($validated['operating_hours'] ?? null, []);
        $servicesOffered = $this->decodeJsonField($validated['services_offered'] ?? null, []);

        $logoPath = $shelter->logo;
        if ($logo) {
            $logoPath = $logo->store('shelter-logos', 'public');
        }

        $docsPaths = is_array($shelter->verification_docs) ? $shelter->verification_docs : [];
        foreach ($verificationDocs as $doc) {
            $path = $doc->store('shelter-verification-docs', 'public');
            if (is_string($path) && $path !== '') {
                $docsPaths[] = $path;
            }
        }

        $shelter->fill([
            'organization_name' => $this->nullableString($validated['organization_name'] ?? null),
            'website' => $this->nullableString($validated['website'] ?? null),
            'ein_tax_id' => $this->nullableString($validated['ein_tax_id'] ?? null),
            'physical_address' => $this->nullableString($validated['physical_address'] ?? null),
            'bio_mission' => $this->nullableString($validated['bio_mission'] ?? null),
            'logo' => $logoPath,
            'verification_docs' => $docsPaths,
            'shelter_type' => $this->nullableString($validated['shelter_type'] ?? null),
            'max_capacity' => isset($validated['max_capacity']) ? (int) $validated['max_capacity'] : null,
            'facilities' => $facilities,
            'operating_hours' => $operatingHours,
            'services_offered' => $servicesOffered,
            'adoption_requirements' => $this->nullableString($validated['adoption_requirements'] ?? null),
            // Keep legacy fields populated for compatibility with existing consumers.
            'shelter_name' => $this->nullableString($validated['organization_name'] ?? null) ?? 'Unnamed Shelter',
            'description' => $this->nullableString($validated['bio_mission'] ?? null),
            'address' => $this->nullableString($validated['physical_address'] ?? null),
            'verification_status' => $shelter->verification_status ?: 'pending',
        ]);

        if (isset($validated['latitude']) || isset($validated['longitude'])) {
            $address = $shelter->address_id ? Address::query()->find($shelter->address_id) : null;
            if (! $address) {
                $address = new Address();
            }
            $address->latitude = isset($validated['latitude']) ? (float) $validated['latitude'] : null;
            $address->longitude = isset($validated['longitude']) ? (float) $validated['longitude'] : null;
            $address->full_address = $this->nullableString($validated['physical_address'] ?? null);
            $address->save();
            $shelter->address_id = $address->id;
        }

        $shelter->save();
        $shelter->loadMissing('addressRecord');

        return $shelter;
    }

    /**
     * @param  mixed  $value
     * @param  array<int|string, mixed>  $fallback
     * @return array<int|string, mixed>
     */
    private function decodeJsonField(mixed $value, array $fallback): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return $fallback;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $fallback;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}

