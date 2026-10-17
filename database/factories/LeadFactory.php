<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'company' => fake()->company(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'website' => 'https://'.fake()->domainName(),
            'message' => fake()->sentence(12),
            'status' => LeadStatus::New,
            'locale' => 'en',
        ];
    }
}
