<?php

namespace Database\Factories;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'company' => fake()->company(),
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'status' => ContactMessageStatus::New,
            'locale' => 'en',
        ];
    }
}
