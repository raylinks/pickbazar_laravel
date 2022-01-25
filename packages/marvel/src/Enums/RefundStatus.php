<?php


namespace Marvel\Enums;

use BenSampo\Enum\Enum;

/**
 * Class RoleType
 * @package App\Enums
 */
final class RefundStatus extends Enum
{
    public const APPROVED = 'approved';
    public const PENDING = 'pending';
    public const REJECTED = 'rejected';
    public const PROCESSING = 'processing';
}
