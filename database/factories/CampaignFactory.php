<?php

namespace Database\Factories;

use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->words(2, true)).' sale';

        return [
            'name' => $name,
            'type' => 'sale',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
            'headline' => ['en' => $name, 'dv' => $name],
            'subheadline' => ['en' => 'Big savings this week', 'dv' => 'މި ހަފުތާގައި ބޮޑު ޑިސްކައުންޓް'],
            'cta_text' => ['en' => 'Shop the sale', 'dv' => 'ސޭލް ބައްލަވާ'],
            'theme_colour' => '#0E7C86',
            'is_active' => true,
            'discount_percent' => 10,
            'placement' => 'home_hero',
            'sort_order' => 0,
        ];
    }
}
