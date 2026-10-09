<?php

namespace Database\Seeders;

use App\Models\TicketCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/*
 * Populates standardized consumer complaint and service request categories.
 * Enables structured ticket intake, classification, and reporting metrics.
 */
class TicketCategorySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $categories = [
            [
                'category_name' => 'Power Interruption / Blackout',
                'category_desc' => 'Unscheduled complete power loss, localized feeder trip, or neighborhood-wide outage.',
            ],
            [
                'category_name' => 'Fluctuating / Low Voltage',
                'category_desc' => 'Abnormal voltage levels causing flickering lights, appliance underperformance, or brownouts.',
            ],
            [
                'category_name' => 'Defective / Damaged Meter',
                'category_desc' => 'Broken glass, stopped disc/digital readout, burnt meter base, or suspected calibration drift.',
            ],
            [
                'category_name' => 'Sparking / Burning Equipment',
                'category_desc' => 'Urgent electrical hazard involving smoking transformers, arcing drop wires, or sparking fuse cutouts.',
            ],
            [
                'category_name' => 'Leaning / Rotten Electric Post',
                'category_desc' => 'Physical infrastructure hazard: fractured concrete pole, rotting wood post, or vehicular collision damage.',
            ],
            [
                'category_name' => 'Line Clearing / Tree Trimming',
                'category_desc' => 'Foliage or tree branches contacting or endangering primary distribution lines.',
            ],
            [
                'category_name' => 'Reconnection Request',
                'category_desc' => 'Consumer reconnection following settled billing arrears or temporary disconnection.',
            ],
            [
                'category_name' => 'Billing & Account Dispute',
                'category_desc' => 'Inquiries regarding unexpected consumption spikes, uncredited payments, or tariff calculation discrepancies.',
            ],
        ];

        foreach ($categories as $cat) {
            TicketCategory::firstOrCreate(['category_name' => $cat['category_name']], $cat);
        }
    }
}
