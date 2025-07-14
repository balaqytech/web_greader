<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EnrollmentStatus: string implements HasLabel
{
    case DRAFT = 'draft'; // مسودة - ولي الأمر بدأ التسجيل ولم يكتمل
    case PENDING = 'pending'; // قيد الانتظار - تم تقديم الطلب
    case SIGNED = 'signed'; // موقعة - تم توقيع العقد
    case APPROVED = 'approved'; // معتمدة - تم اعتماد الطلب
    case REJECTED = 'rejected'; // مرفوضة - تم رفض الطلب
    case CANCELED = 'canceled'; // ملغاة - تم إلغاء الطلب
    case COMPLETED = 'completed';   // مكتملة - تم إكمال التسجيل (كل الأقساط مدفوعة)

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => __('admin.enrollment.statuses.draft'),
            self::PENDING => __('admin.enrollment.statuses.pending'),
            self::SIGNED => __('admin.enrollment.statuses.signed'),
            self::APPROVED => __('admin.enrollment.statuses.approved'),
            self::REJECTED => __('admin.enrollment.statuses.rejected'),
            self::CANCELED => __('admin.enrollment.statuses.canceled'),
            self::COMPLETED => __('admin.enrollment.statuses.completed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PENDING => 'info',
            self::SIGNED => 'success',
            self::APPROVED => 'primary',
            self::REJECTED => 'danger',
            self::CANCELED => 'secondary',
            self::COMPLETED => 'dark',
        };
    }
}
