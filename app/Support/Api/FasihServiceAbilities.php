<?php

declare(strict_types=1);

namespace App\Support\Api;

/**
 * The one canonical list of Sanctum abilities the Fasih service account may hold. Token
 * issuance (`fasih:issue-token`) mints exactly this set, and every protected API route
 * constrains itself to one of these strings via the `abilities:*` middleware. Keeping the
 * source of truth here means a new capability is added in one place and cannot drift between
 * the issuer and the route guards.
 */
final class FasihServiceAbilities
{
    public const LeadsCreate = 'leads:create';

    public const LeadsRead = 'leads:read';

    public const BotContactsManage = 'bot-contacts:manage';

    public const AssessmentsManage = 'assessments:manage';

    public const ApplicationsStatus = 'applications:status';

    public const PaymentsInitiate = 'payments:initiate';

    public const PaymentsUploadReceipt = 'payments:upload-receipt';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::LeadsCreate,
            self::LeadsRead,
            self::BotContactsManage,
            self::AssessmentsManage,
            self::ApplicationsStatus,
            self::PaymentsInitiate,
            self::PaymentsUploadReceipt,
        ];
    }
}
