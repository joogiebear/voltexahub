<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\StoreItem;
use Illuminate\Database\Seeder;

class StoreItemSeeder extends Seeder
{
    public function run(): void
    {
        $minecraft = Game::where('slug', 'minecraft')->first();
        $rust = Game::where('slug', 'rust')->first();

        // Core items — no game required, always seeded
        $items = [
            [
                'name' => '500 Credits Pack',
                'slug' => '500-credits',
                'description' => 'Get 500 forum credits to spend in the store.',
                'icon' => '💰',
                'category' => 'currency_kits',
                'price_money' => 2.99,
                'supports_both' => false,
                'item_type' => 'currency',
                'item_value' => '500',
                'display_order' => 1,
            ],
            [
                'name' => '2,000 Credits Pack',
                'slug' => '2000-credits',
                'description' => 'Get 2,000 forum credits — great value!',
                'icon' => '💎',
                'category' => 'currency_kits',
                'price_money' => 9.99,
                'supports_both' => false,
                'item_type' => 'currency',
                'item_value' => '2000',
                'display_order' => 2,
            ],
            [
                'name' => 'Custom Name Color',
                'slug' => 'custom-name-color',
                'description' => 'Choose a custom color for your forum username.',
                'icon' => '🎨',
                'category' => 'cosmetics',
                'price_credits' => 250,
                'supports_both' => false,
                'item_type' => 'cosmetic',
                'display_order' => 3,
            ],
            [
                'name' => 'Animated Avatar Border',
                'slug' => 'animated-avatar-border',
                'description' => 'Get an animated border around your profile avatar.',
                'icon' => '✨',
                'category' => 'cosmetics',
                'price_credits' => 500,
                'supports_both' => false,
                'item_type' => 'cosmetic',
                'display_order' => 4,
            ],
            [
                'name' => 'Profile Banner',
                'slug' => 'profile-banner',
                'description' => 'Upload a custom banner for your profile page.',
                'icon' => '🖼️',
                'category' => 'cosmetics',
                'price_credits' => 300,
                'supports_both' => false,
                'item_type' => 'cosmetic',
                'display_order' => 5,
            ],
            [
                'name' => 'Fire Flair',
                'slug' => 'fire-flair',
                'description' => 'Add a fire emoji flair next to your username.',
                'icon' => '🔥',
                'category' => 'flair',
                'price_credits' => 100,
                'supports_both' => false,
                'item_type' => 'flair',
                'item_value' => '🔥',
                'display_order' => 6,
            ],
            [
                'name' => 'Diamond Flair',
                'slug' => 'diamond-flair',
                'description' => 'Add a diamond emoji flair next to your username.',
                'icon' => '💎',
                'category' => 'flair',
                'price_credits' => 150,
                'supports_both' => false,
                'item_type' => 'flair',
                'item_value' => '💎',
                'display_order' => 7,
            ],
            [
                'name' => 'Crown Flair',
                'slug' => 'crown-flair',
                'description' => 'Add a crown emoji flair next to your username.',
                'icon' => '👑',
                'category' => 'flair',
                'price_credits' => 200,
                'supports_both' => false,
                'item_type' => 'flair',
                'item_value' => '👑',
                'display_order' => 8,
            ],
        ];

        // Minecraft-specific items (only if game exists)
        if ($minecraft) {
            $items = array_merge($items, [
                [
                    'name' => 'VIP Rank',
                    'slug' => 'vip-rank',
                    'description' => 'Get VIP rank with exclusive perks and a colored name.',
                    'icon' => '⭐',
                    'category' => 'ranks',
                    'price_money' => 9.99,
                    'price_credits' => 500,
                    'supports_both' => true,
                    'item_type' => 'rank',
                    'item_value' => 'lp user %player% parent set vip',
                    'game_id' => $minecraft->id,
                    'display_order' => 9,
                ],
                [
                    'name' => 'Elite Rank',
                    'slug' => 'elite-rank',
                    'description' => 'Elite rank with premium features and priority support.',
                    'icon' => '👑',
                    'category' => 'ranks',
                    'price_money' => 19.99,
                    'price_credits' => 1000,
                    'supports_both' => true,
                    'item_type' => 'rank',
                    'item_value' => 'lp user %player% parent set elite',
                    'game_id' => $minecraft->id,
                    'display_order' => 10,
                ],
            ]);
        }

        // Rust-specific items (only if game exists)
        if ($rust) {
            $items = array_merge($items, [
                [
                    'name' => 'Rust VIP',
                    'slug' => 'rust-vip',
                    'description' => 'VIP status on Rust servers with exclusive kits.',
                    'icon' => '⭐',
                    'category' => 'ranks',
                    'price_money' => 14.99,
                    'price_credits' => 750,
                    'supports_both' => true,
                    'item_type' => 'rank',
                    'item_value' => 'oxide.grant user %steamid% vip',
                    'game_id' => $rust->id,
                    'display_order' => 11,
                ],
                [
                    'name' => 'Rust Starter Kit',
                    'slug' => 'rust-starter-kit',
                    'description' => 'Get a starter kit on wipe day.',
                    'icon' => '📦',
                    'category' => 'currency_kits',
                    'price_money' => 4.99,
                    'price_credits' => 200,
                    'supports_both' => true,
                    'item_type' => 'kit',
                    'item_value' => 'kit.give %steamid% starter',
                    'game_id' => $rust->id,
                    'display_order' => 12,
                ],
            ]);
        }

        foreach ($items as $item) {
            StoreItem::updateOrCreate(['slug' => $item['slug']], $item);
        }
    }
}
