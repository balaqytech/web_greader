<?php

return [
    'auth' => [
        'login' => [
            'title' => 'تسجيل الدخول',
            'description' => 'أدخل تفاصيلك أدناه لتسجيل الدخول كمسوق',
            'whatsapp' => 'رقم الجوال',
            'whatsapp_placeholder' => 'أدخل رقم الجوال',
            'password' => 'كلمة المرور',
            'password_placeholder' => 'أدخل كلمة المرور',
            'remember_me' => 'تذكرني',
            'login' => 'تسجيل الدخول كمسوق',
            'forgot_password' => 'نسيت كلمة المرور؟',
            'register' => 'إنشاء حساب جديد',
            'dont_have_account' => 'لا يوجد لديك حساب كمسوق؟',
        ],
        'register' => [
            'title' => 'إنشاء حساب جديد كمسوق',
            'description' => 'أدخل تفاصيلك أدناه للتسجيل كمسوق. سيتم مراجعة حسابك من قبل الادارة والموافقة عليه في اقرب وقت ممكن',
            'name' => 'الاسم',
            'name_placeholder' => 'أدخل اسمك الكامل',
            'whatsapp' => 'رقم الجوال',
            'whatsapp_placeholder' => 'يفضل ان يكون رقم واتساب',
            'password' => 'كلمة المرور',
            'password_placeholder' => 'أدخل كلمة المرور',
            'password_confirmation' => 'تأكيد كلمة المرور',
            'password_confirmation_placeholder' => 'أدخل تأكيد كلمة المرور',
            'register' => 'إنشاء حساب كمسوق',
            'login' => 'تسجيل الدخول',
            'have_account' => 'لديك حساب مسوق بالفعل؟',
        ],
        'logout' => [
            'title' => 'تسجيل الخروج',
        ],
        'navigation' => [
            'dashboard' => 'لوحة التحكم',
            'logout' => 'تسجيل الخروج',
        ],
        'alerts' => [
            'login' => [
                'success' => 'تم تسجيل الدخول بنجاح',
                'wrong_credentials' => 'رقم الجوال أو كلمة المرور غير صحيحة',
                'wait_for_approval' => 'حسابك غير مفعل، يرجى الانتظار حتى يتم الموافقة على الحساب من قبل الادارة',
            ],
            'register' => [
                'success' => 'تم إنشاء الحساب بنجاح، يرجى الانتظار حتى يتم الموافقة على الحساب من قبل الادارة',
                'error' => 'حدث خطأ أثناء إنشاء الحساب',
            ],
            'logout' => [
                'success' => 'تم تسجيل الخروج بنجاح',
                'error' => 'حدث خطأ أثناء تسجيل الخروج',
            ],
        ],
    ],
    'dashboard' => [
        'title' => 'لوحة التحكم',
        'greeting' => 'مرحباً بك مجدداً، :name!',
        'your_affiliate_code_is' => 'رمز التسويق الخاص بك هو:',
        'total_leads' => 'إجمالي العملاء المحتملين',
        'all_leads_referred_by_you' => 'جميع العملاء المحتملين الذين أحلتهم',
        'your_affiliate_code_is' => 'رمز التسويق الخاص بك هو:',
        'your_affiliate_link_is' => 'رابط التسويق الخاص بك هو:',
        'download_qr_code' => 'تحميل رمز QR',
    ],
    'settings' => [
        'title' => 'الإعدادات',
    ],
    'profile' => [
        'title' => 'الملف الشخصي',
        'description' => 'قم بتحديث اسمك ورقم جوالك',
        'name' => 'الاسم',
        'name_placeholder' => 'أدخل اسمك الكامل',
        'whatsapp' => 'رقم الجوال',
        'whatsapp_placeholder' => 'أدخل رقم الجوال',
        'email' => 'البريد الإلكتروني',
        'email_placeholder' => 'أدخل البريد الإلكتروني',
        'save' => 'حفظ',
        'alerts' => [
            'updated' => 'تم تحديث الملف الشخصي بنجاح',
        ],
    ],
    'password' => [
        'title' => 'تغيير كلمة المرور',
        'description' => 'تأكد من استخدام كلمة مرور طويلة وعشوائية للحفاظ على أمان حسابك',
        'current_password' => 'كلمة المرور الحالية',
        'current_password_placeholder' => 'أدخل كلمة المرور الحالية',
        'new_password' => 'كلمة المرور الجديدة',
        'new_password_placeholder' => 'أدخل كلمة المرور الجديدة',
        'confirm_password' => 'تأكيد كلمة المرور',
        'confirm_password_placeholder' => 'أدخل تأكيد كلمة المرور الجديدة',
        'save' => 'حفظ',
        'alerts' => [
            'updated' => 'تم تحديث كلمة المرور بنجاح',
            'current_password_incorrect' => 'كلمة المرور الحالية غير صحيحة',
        ],
    ],
];
