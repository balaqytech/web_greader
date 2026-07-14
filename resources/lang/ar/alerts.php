<?php

return [
    'reading_assessment_form_submissions' => [
        'already_exists' => 'تم إرسال هذا النموذج من قبل بالفعل',
    ],
    'application' => [
        'transitioned_to_submitted' => 'تم اكمال بيانات الطلب بنجاح',
        'application_student_is_required' => 'بيانات الطالب مطلوبة',
        'application_student_name_is_required' => 'اسم الطالب مطلوب',
        'application_student_civil_number_is_required' => 'الرقم المدني للطالب مطلوب',
        'program_branch_and_season_are_required' => 'البرنامج والفرع والموسم مطلوبون',
        'exactly_one_guardian_contact_is_required' => 'يجب أن يكون هناك جهة اتصال واحدة فقط من أولياء الأمور',
        'guardian_must_have_name_phone_and_id_number' => 'يجب أن يكون لدى ولي الأمر اسم ورقم هاتف ورقم هوية',
        'at_least_two_non_guardian_emergency_contacts_are_required' => 'يجب أن يكون هناك على الأقل جهتي اتصال للطوارئ من غير أولياء الأمور',
        'application_contract_is_not_signed' => 'عقد الطالب غير موقع',
        'application_not_waiting_for_contract' => 'الطلب ليس بانتظار توقيع العقد',
        'contract_token_invalid_or_expired' => 'رابط توقيع العقد غير صالح أو منتهي الصلاحية',
        'invalid_signature_data' => 'بيانات التوقيع غير صحيحة',
        'invalid_signature_format' => 'تنسيق التوقيع غير صحيح',
        'invalid_signature_image' => 'ملف التوقيع ليس صورة PNG صالحة',
        'signature_too_large' => 'حجم التوقيع أكبر من الحد المسموح',
        'application_contract_signed_online_by_applicant' => 'تم توقيع العقد عبر الإنترنت من قبل ولي الأمر',

        // Baseline hardening (Phase 0)
        'student_name_required' => 'اسم الطالب مطلوب.',
        'student_civil_number_required' => 'الرقم المدني للطالب مطلوب.',
        'guardian_required' => 'بيانات ولي الأمر مطلوبة.',
        'contract_not_signed' => 'يجب توقيع العقد قبل الانتقال إلى مراجعة الفرع.',
        'contract_missing' => 'لا يوجد عقد مرتبط بهذا الطلب.',
        'uploaded_file_missing' => 'الملف الذي تم رفعه غير موجود.',
        'application_contract_uploaded_by_staff' => 'تم رفع نسخة العقد الموقّعة بواسطة الموظف.',
        'rejection_reason_required' => 'سبب الرفض مطلوب.',
        'cancellation_note_required' => 'سبب الإلغاء مطلوب.',
        'unexpected_database_error' => 'حدث خطأ غير متوقع أثناء حفظ البيانات. يرجى المحاولة مرة أخرى.',
        'lead_already_converted' => 'العميل المحتمل :ref_no محوّل بالفعل إلى طلب تسجيل.',

        // Guardian uniqueness conflicts during acceptance
        'guardian_phone_conflict' => 'رقم الهاتف :phone مسجّل بالفعل لولي أمر آخر.',
    ],
];
