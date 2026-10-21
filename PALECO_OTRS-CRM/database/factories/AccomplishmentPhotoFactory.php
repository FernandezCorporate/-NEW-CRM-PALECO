<?php

namespace Database\Factories;

use App\Models\AccomplishmentPhoto;
use App\Models\TicketAccomplishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccomplishmentPhoto>
 */
class AccomplishmentPhotoFactory extends Factory
{
    protected $model = AccomplishmentPhoto::class;

    public function definition(): array
    {
        return [
            'accomplishment_id' => TicketAccomplishment::first()?->id ?? TicketAccomplishment::factory(),
            'file_path' => 'accomplishment_photos/test_photo.jpg',
            'file_name' => 'test_photo.jpg',
            'file_size' => 102400,
            'mime_type' => 'image/jpeg',
        ];
    }
}
