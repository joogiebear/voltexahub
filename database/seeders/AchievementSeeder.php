<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            [
                'name' => 'First Post',
                'description' => 'Make your very first post on the forums',
                'icon' => '✏️',
                'category' => 'posts',
                'trigger_type' => 'count',
                'trigger_key' => 'post_count',
                'trigger_value' => 1,
                'credits_reward' => 10,
            ],
            [
                'name' => 'Conversation Starter',
                'description' => 'Create 10 discussion threads',
                'icon' => '💡',
                'category' => 'posts',
                'trigger_type' => 'count',
                'trigger_key' => 'thread_count',
                'trigger_value' => 10,
                'credits_reward' => 25,
            ],
            [
                'name' => 'Regular',
                'description' => 'Make 50 posts across the forums',
                'icon' => '📝',
                'category' => 'posts',
                'trigger_type' => 'count',
                'trigger_key' => 'post_count',
                'trigger_value' => 50,
                'credits_reward' => 50,
            ],
            [
                'name' => 'Prolific Poster',
                'description' => 'Reach 100 total posts',
                'icon' => '📚',
                'category' => 'posts',
                'trigger_type' => 'count',
                'trigger_key' => 'post_count',
                'trigger_value' => 100,
                'credits_reward' => 100,
            ],
            [
                'name' => 'Veteran',
                'description' => 'Make 500 posts — you\'re a true community veteran',
                'icon' => '🏆',
                'category' => 'posts',
                'trigger_type' => 'count',
                'trigger_key' => 'post_count',
                'trigger_value' => 500,
                'credits_reward' => 250,
            ],
            [
                'name' => 'One Year Club',
                'description' => 'Be a member for one full year',
                'icon' => '🎂',
                'category' => 'time',
                'trigger_type' => 'action',
                'trigger_key' => 'account_age_days',
                'trigger_value' => 365,
                'credits_reward' => 100,
            ],
            [
                'name' => 'Two Year Club',
                'description' => 'Be a member for two full years',
                'icon' => '🎉',
                'category' => 'time',
                'trigger_type' => 'action',
                'trigger_key' => 'account_age_days',
                'trigger_value' => 730,
                'credits_reward' => 200,
            ],
            [
                'name' => 'Social Butterfly',
                'description' => 'Receive 50 reactions on your posts',
                'icon' => '🦋',
                'category' => 'social',
                'trigger_type' => 'count',
                'trigger_key' => 'reactions_received',
                'trigger_value' => 50,
                'credits_reward' => 50,
            ],
            [
                'name' => 'Beloved',
                'description' => 'Receive 100 reactions on your posts',
                'icon' => '❤️',
                'category' => 'social',
                'trigger_type' => 'count',
                'trigger_key' => 'reactions_received',
                'trigger_value' => 100,
                'credits_reward' => 100,
            ],
            [
                'name' => 'First Purchase',
                'description' => 'Make your first store purchase',
                'icon' => '🛒',
                'category' => 'shop',
                'trigger_type' => 'action',
                'trigger_key' => 'purchases',
                'trigger_value' => 1,
                'credits_reward' => 25,
            ],
            [
                'name' => 'Big Spender',
                'description' => 'Spend 1,000 credits in the store',
                'icon' => '💰',
                'category' => 'shop',
                'trigger_type' => 'count',
                'trigger_key' => 'credits_spent',
                'trigger_value' => 1000,
                'credits_reward' => 50,
            ],
            [
                'name' => 'Helper',
                'description' => 'Have 10 of your replies mark threads as solved',
                'icon' => '🤝',
                'category' => 'social',
                'trigger_type' => 'count',
                'trigger_key' => 'solutions',
                'trigger_value' => 10,
                'credits_reward' => 75,
            ],
        ];

        foreach ($achievements as $achievement) {
            Achievement::updateOrCreate(['name' => $achievement['name']], $achievement);
        }
    }
}
