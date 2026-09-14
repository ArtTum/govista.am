<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            ['key' => 'phone', 'value' => '041999033', 'group' => 'general', 'type' => 'text'],
            ['key' => 'email', 'value' => 'info@govista.am', 'group' => 'general', 'type' => 'text'],
            ['key' => 'whatsapp', 'value' => 'https://wa.me/37441999033', 'group' => 'social', 'type' => 'url'],
        ];

        DB::transaction(function () use ($settings): void {
            foreach ($settings as $setting) {
                $existing = DB::table('settings')->where('key', $setting['key'])->exists();
                $values = [
                    'value' => json_encode($setting['value'], JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ];

                if ($existing) {
                    DB::table('settings')->where('key', $setting['key'])->update($values);
                } else {
                    DB::table('settings')->insert([
                        ...$setting,
                        ...$values,
                        'created_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Contact details remain admin-managed; do not restore obsolete contacts on rollback.
    }
};
