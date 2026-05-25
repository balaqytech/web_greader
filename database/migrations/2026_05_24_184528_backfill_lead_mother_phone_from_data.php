<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('leads', 'mother_phone')) {
            return;
        }

        DB::table('leads')
            ->whereNotNull('data')
            ->orderBy('id')
            ->each(function (object $lead): void {
                $data = $this->decodeData($lead->data);

                if (! array_key_exists('mother_phone', $data)) {
                    return;
                }

                $motherPhone = $data['mother_phone'];
                unset($data['mother_phone']);

                $updates = [
                    'data' => $data === [] ? null : json_encode($data, JSON_UNESCAPED_UNICODE),
                ];

                if ($lead->mother_phone === null && $motherPhone !== null) {
                    $updates['mother_phone'] = (string) $motherPhone;
                }

                DB::table('leads')
                    ->where('id', $lead->id)
                    ->update($updates);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('leads', 'mother_phone')) {
            return;
        }

        DB::table('leads')
            ->whereNotNull('mother_phone')
            ->orderBy('id')
            ->each(function (object $lead): void {
                $data = $this->decodeData($lead->data);
                $data['mother_phone'] = $lead->mother_phone;

                DB::table('leads')
                    ->where('id', $lead->id)
                    ->update([
                        'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    ]);
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeData(mixed $data): array
    {
        if (is_array($data)) {
            return $data;
        }

        if (! is_string($data) || $data === '') {
            return [];
        }

        $decoded = json_decode($data, true);

        return is_array($decoded) ? $decoded : [];
    }
};
