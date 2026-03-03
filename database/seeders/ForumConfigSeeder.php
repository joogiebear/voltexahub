<?php

namespace Database\Seeders;

use App\Models\ForumConfig;
use Illuminate\Database\Seeder;

class ForumConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            'forum_name' => 'My Forum',
            'multi_game_mode' => 'false',
            'multi_game' => 'false',
            'maintenance_mode' => 'false',
            'accent_color' => '#7c3aed',
            'rcon_host_minecraft' => '',
            'rcon_port_minecraft' => '25575',
            'rcon_password_minecraft' => '',
            'rcon_host_rust' => '',
            'rcon_port_rust' => '28016',
            'rcon_password_rust' => '',
            'credits_per_thread' => '10',
            'credits_per_reply' => '5',
            'credits_for_solved' => '25',
            'credits_per_like' => '1',
            'credits_per_like_given' => '0',
            'credits_daily_post_limit' => '50',
            'role_credit_multipliers' => '{"admin":1.0,"moderator":1.0,"member":1.0}',

            // Email / SMTP (empty = use .env defaults)
            'mail_mailer'       => '',
            'mail_host'         => '',
            'mail_port'         => '587',
            'mail_username'     => '',
            'mail_password'     => '',
            'mail_encryption'   => 'tls',
            'mail_from_address' => '',
            'mail_from_name'    => '',
        ];

        foreach ($configs as $key => $value) {
            ForumConfig::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
