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
            'is_multi_game' => 'false',
            'multi_game_mode' => 'false',
            'multi_game' => 'false',
            'default_game_id' => '',
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

            // Logo
            'logo_type'       => 'both',       // both | icon_only | text_only | image
            'logo_icon'       => 'fa-solid fa-bolt',
            'logo_icon_color' => '#7c3aed',
            'logo_image'      => '',

            // Custom CSS/JS
            'custom_css' => '',
            'custom_js' => '',

            // Usergroup Legend
            'show_usergroup_legend'    => 'true',
            'usergroup_legend_groups'  => json_encode(['admin','moderator','vip','elite','member']),

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
