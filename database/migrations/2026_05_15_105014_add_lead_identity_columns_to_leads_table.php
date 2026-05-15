<?php

use App\Support\LeadDuplicateMerger;
use App\Support\LeadIdentityNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('leads', 'student_name_normalized')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('student_name_normalized')->nullable()->after('student_name');
                $table->char('identity_fingerprint', 64)->nullable()->after('student_name_normalized');
            });
        }

        $this->backfillIdentityColumns();

        app(LeadDuplicateMerger::class)->mergeExactFingerprintDuplicates();

        $this->enforceNotNullIdentityColumns();

        if (Schema::hasIndex('leads', 'leads_unique')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropUnique('leads_unique');
            });
        }

        if (! Schema::hasIndex('leads', 'leads_identity_unique')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->unique(
                    ['whatsapp', 'program_id', 'season_id', 'branch_id', 'identity_fingerprint'],
                    'leads_identity_unique',
                );
            });
        }

        if (! Schema::hasIndex('leads', 'leads_scope_lookup_index')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->index(
                    ['whatsapp', 'program_id', 'season_id', 'branch_id'],
                    'leads_scope_lookup_index',
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('leads', 'leads_identity_unique')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropUnique('leads_identity_unique');
            });
        }

        if (Schema::hasIndex('leads', 'leads_scope_lookup_index')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropIndex('leads_scope_lookup_index');
            });
        }

        if (Schema::hasColumn('leads', 'student_name_normalized')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn(['student_name_normalized', 'identity_fingerprint']);
            });
        }

        if (! Schema::hasIndex('leads', 'leads_unique')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->unique(
                    ['whatsapp', 'program_id', 'season_id', 'branch_id', 'student_name'],
                    'leads_unique',
                );
            });
        }
    }

    private function backfillIdentityColumns(): void
    {
        $normalizer = app(LeadIdentityNormalizer::class);

        DB::table('leads')->orderBy('id')->each(function (object $lead) use ($normalizer): void {
            DB::table('leads')->where('id', $lead->id)->update([
                'student_name_normalized' => $normalizer->normalizeName($lead->student_name),
                'identity_fingerprint' => $normalizer->fingerprint(
                    $lead->whatsapp,
                    (int) $lead->program_id,
                    (int) $lead->season_id,
                    $lead->branch_id !== null ? (int) $lead->branch_id : null,
                    $lead->student_name,
                ),
            ]);
        });
    }

    private function enforceNotNullIdentityColumns(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE leads MODIFY student_name_normalized VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE leads MODIFY identity_fingerprint CHAR(64) NOT NULL');
    }
};
