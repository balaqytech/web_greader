<?php

return [
    'navigation_groups' => [
        'settings' => 'الإعدادات',
    ],
    'navigation' => [
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
        'academic' => 'أكاديمي',
        'summer' => 'صيفي',
    ],
    'season' => [
        'label' => 'موسم',
        'plural_label' => 'المواسم',
        'name' => 'اسم الموسم',
        'type' => 'نوع الموسم',
        'start_date' => 'تاريخ البدء',
        'end_date' => 'تاريخ الانتهاء',
        'is_active' => 'تفعيل',
        'is_registration_open' => 'فتح التسجيل',
        'is_closed' => 'مغلق',
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
];
