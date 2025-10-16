<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FormDetails>
 */
class FormDetailsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => \App\Models\Form::factory(),
            'field_name' => fake()->word(),
            'field_type' => fake()->randomElement(['text', 'radio', 'checkbox', 'select']),
            'field_value' => fake()->word(),
            'is_required' => fake()->boolean(),
        ];
    }
}
