<?php

namespace Database\Seeders;

use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the Shield permission/role foundation Phase 1 tenancy relies on: model-specific
 * cross-branch permissions (`ViewAllBranches:{Model}`) for every currently and soon-to-be
 * branch-scoped model, the finance permission set, the Application permission matrix, the
 * deliberately-unassigned payment permissions, the `Access:Panel` escape-hatch permission,
 * and the `branch_staff`/`branch_manager`/`central_finance` roles. Idempotent — safe to run
 * repeatedly, including after `ShieldSeeder`, whose generated, hardcoded permission list only
 * knows about the resources it was regenerated against and does not cover these.
 *
 * `branch_staff`/`branch_manager` are synced to exactly `BRANCH_APPLICATION_PERMISSIONS` (no
 * cross-branch permission, no raw `Delete:Application`) — this seeder is these two roles'
 * only owner of Application permissions right now, so syncPermissions() here can never leave
 * drift without also being the seeder that would need updating to add more. `central_finance`
 * receives exactly `ViewAllBranches:Payment` plus its three finance permissions via
 * syncPermissions(), so re-running this seeder can never leave it with anything model-scoped
 * for Application/Lead/Student/document/assessment models — and notably never
 * `ViewAllBranches:Application`, which finance must not hold: it reaches application data
 * only through the payment projection, never through an unscoped Application query.
 *
 * `config/filament-shield.php` has `super_admin.define_via_gate = false`, so — unlike the
 * `intercept_gate` mode — `super_admin` is a real role that must actually hold every
 * permission to be unrestricted; it does not bypass Gate checks implicitly. This seeder
 * syncs `super_admin` to every `web`-guard permission that exists once this seeder's own
 * permissions have been created, so it always includes them (and anything `ShieldSeeder`
 * generated) without needing its own hardcoded, driftable list.
 */
class ShieldPermissionSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const CROSS_BRANCH_PERMISSIONS = [
        'ViewAllBranches:Application',
        'ViewAllBranches:Lead',
        'ViewAllBranches:Student',
        'ViewAllBranches:ReadingAssessmentFormSubmission',
        'ViewAllBranches:Payment',
        'ViewAllBranches:ApplicationDocument',
    ];

    /**
     * The complete central-finance payment ability set. Refunds are out of scope for the
     * payment domain — there is no refunded state, column, action, or API call — so there is
     * deliberately no `Refund:Payment` here; see `PRUNED_PERMISSIONS`.
     *
     * @var list<string>
     */
    private const FINANCE_PERMISSIONS = [
        'ViewAny:Payment',
        'View:Payment',
        'VerifyBankTransfer:Payment',
    ];

    /**
     * Real, assignable permissions that are created but deliberately granted to no ordinary
     * role. `super_admin` still receives them via the blanket sync below; everyone else must
     * be granted them explicitly and consciously.
     *
     * - `ConfirmCash:Payment` — confirming cash is an unsupervised way to mark a fee paid
     *   without money moving through any verifiable channel, so no branch or finance role
     *   carries it by default.
     * - `Manage:PaymentSettings` — sets the registration fee itself, i.e. what every future
     *   application is charged.
     *
     * @var list<string>
     */
    private const UNASSIGNED_PAYMENT_PERMISSIONS = [
        'ConfirmCash:Payment',
        'Manage:PaymentSettings',
    ];

    /**
     * Permissions this seeder previously created and must now actively remove. Dropping a
     * name from the lists above only stops it being created on a fresh database — on an
     * already-seeded one the row survives, stays attached to `super_admin` through the
     * blanket sync, and lingers as drift. Deleting it cascades the role/permission pivot
     * rows, and nothing references it: the payment domain implements no refund workflow.
     *
     * @var list<string>
     */
    private const PRUNED_PERMISSIONS = [
        'Refund:Payment',
    ];

    /**
     * @var list<string>
     */
    private const PANEL_PERMISSIONS = [
        'Access:Panel',
    ];

    /**
     * Standard Shield CRUD plus the approved-workflow custom abilities. `Delete:Application`
     * exists here (so it's a real, assignable permission) but is deliberately excluded from
     * `BRANCH_APPLICATION_PERMISSIONS` — no approved rule requires raw deletion for
     * `branch_staff`/`branch_manager`.
     *
     * @var list<string>
     */
    private const APPLICATION_PERMISSIONS = [
        'ViewAny:Application',
        'View:Application',
        'Create:Application',
        'Update:Application',
        'Delete:Application',
        'GenerateContract:Application',
        'UploadSignedContract:Application',
        'Accept:Application',
        'Reject:Application',
        'Cancel:Application',
    ];

    /**
     * @var list<string>
     */
    private const BRANCH_APPLICATION_PERMISSIONS = [
        'ViewAny:Application',
        'View:Application',
        'Create:Application',
        'Update:Application',
        'GenerateContract:Application',
        'UploadSignedContract:Application',
        'Accept:Application',
        'Reject:Application',
        'Cancel:Application',
    ];

    /**
     * @var list<string>
     */
    private const ROLES = ['branch_staff', 'branch_manager', 'central_finance'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionModel = Utils::getPermissionModel();
        $roleModel = Utils::getRoleModel();

        foreach ([
            ...self::CROSS_BRANCH_PERMISSIONS,
            ...self::FINANCE_PERMISSIONS,
            ...self::UNASSIGNED_PAYMENT_PERMISSIONS,
            ...self::PANEL_PERMISSIONS,
            ...self::APPLICATION_PERMISSIONS,
        ] as $permission) {
            $permissionModel::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Before the super_admin sync below, so a pruned permission cannot be re-granted.
        $permissionModel::whereIn('name', self::PRUNED_PERMISSIONS)
            ->where('guard_name', 'web')
            ->get()
            ->each
            ->delete();

        foreach (self::ROLES as $role) {
            $roleModel::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }

        $centralFinance = $roleModel::where('name', 'central_finance')
            ->where('guard_name', 'web')
            ->firstOrFail();

        $centralFinance->syncPermissions([
            'ViewAllBranches:Payment',
            ...self::FINANCE_PERMISSIONS,
        ]);

        $branchStaff = $roleModel::where('name', 'branch_staff')
            ->where('guard_name', 'web')
            ->firstOrFail();

        $branchStaff->syncPermissions(self::BRANCH_APPLICATION_PERMISSIONS);

        $branchManager = $roleModel::where('name', 'branch_manager')
            ->where('guard_name', 'web')
            ->firstOrFail();

        $branchManager->syncPermissions(self::BRANCH_APPLICATION_PERMISSIONS);

        // firstOrCreate rather than assumed-to-exist: this seeder must remain correct even
        // if it is ever run standalone (e.g. in tests) without ShieldSeeder first.
        $superAdmin = $roleModel::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        $superAdmin->syncPermissions(
            $permissionModel::where('guard_name', 'web')->pluck('name')->all()
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Shield permission foundation seeded.');
    }
}
