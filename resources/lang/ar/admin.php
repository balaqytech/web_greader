<?php

return [
    'navigation_groups' => [
        'school' => 'المدرسة',
        'settings' => 'الإعدادات',
    ],
    'navigation' => [
        'programs' => 'البرامج الدراسية والصيفية',
        'branches' => 'الفروع',
        'seasons' => 'المواسم',
    ],
    'branch' => [
        'label' => 'فرع',
        'plural_label' => 'الفروع',
        'name' => 'اسم الفرع',
        'address' => 'عنوان الفرع',
        'phone' => 'رقم الهاتف',
        'mobile' => 'رقم الجوال',
        'is_active' => 'تفعيل',
        'additional_info' => 'معلومات إضافية',
    ],
    'program_type' => [
        'academic' => 'دراسي - رواء',
        'summer' => 'صيفي - سنا',
    ],
    'season' => [
        'label' => 'موسم',
        'plural_label' => 'المواسم',
        'name' => 'اسم الموسم',
        'type' => 'نوع الموسم',
        'start_date' => 'تاريخ البدء',
        'end_date' => 'تاريخ الانتهاء',
        'is_active' => 'تفعيل',
        'closed_at' => 'تاريخ الإغلاق',
        'actions' => [
            'open' => 'فتح',
            'close' => 'إغلاق',
        ],
        'validation' => [
            'cannot_reopen_closed' => 'لا يمكن إعادة فتح موسم مغلق نهائياً.',
            'type_required_to_activate' => 'نوع الموسم مطلوب لتنشيط الموسم.',
            'one_active_per_type' => 'يُسمح بموسم واحد نشط فقط لكل نوع.',
            'max_active_seasons' => 'يمكن أن يكون موسمان نشطان كحد أقصى في نفس الوقت، أحدهما أكاديمي والآخر صيفي.',
        ],
    ],
    'program' => [
        'label' => 'برنامج',
        'plural_label' => 'البرامج',
        'name' => 'اسم البرنامج',
        'type' => 'نوع البرنامج',
        'description' => 'وصف البرنامج',
        'base_price' => 'السعر الأساسي',
        'accept_installments' => 'يقبل الدفع بالتقسيط',
        'contract' => 'العقد',
        'is_open' => 'مفتوح للتسجيل',
        'is_active' => 'تفعيل',
        'sort_order' => 'ترتيب',
        'contract_helper_text' => 'يمكنك استخدام المحرر لإضافة محتوى العقد، بما في ذلك النصوص والصور والجداول.',
    ],
];
