<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $title }}</title>
    <style>
        @page {
            header: page-header;
            footer: page-footer;
        }

        body {
            font-family: 'expo', sans-serif;
        }

        .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #4ade80;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .header-logo {
            height: 50px;
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #166534;
        }

        .header-details {
            text-align: left;
            font-size: 0.9rem;
            color: #4b5563;
        }

        .footer-container {
            width: 100%;
            border-top: 2px solid #4ade80;
            padding-top: 8px;
            font-size: 0.9rem;
            color: #4b5563;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-contact {
            direction: ltr;
        }

        .footer-page {
            font-weight: bold;
            color: #166534;
        }

        /* Remove main margin, handled by @page margins */
    </style>
</head>

<body dir="rtl" class="bg-green-200">
    <htmlpageheader name="page-header">
        <div class="header-container">
            <img src="{{ asset('logo.png') }}" class="header-logo" alt="Logo">
            <div class="header-title">{{ $title ?? 'عقد التسجيل' }}</div>
            <div class="header-details">
                {{-- يمكنك إضافة تفاصيل إضافية هنا مثل تاريخ العقد أو رقم الطالب --}}
                <div>تاريخ: {{ date('Y-m-d') }}</div>
            </div>
        </div>
    </htmlpageheader>

    <main>
        {!! $contract !!}
        <h2 class="text-center mt-6 mb-4">{{ __('frontend.signature') }}</h2>
        <img src="{{ asset($signature) }}" alt="" style="margin-top: 30px; max-width: 200px;">
    </main>

    <htmlpagefooter name="page-footer">
        <div class="footer-container">
            <div class="footer-contact">
                للتواصل: 0123456789 | info@example.com
            </div>
            <div class="footer-page">
                صفحة {PAGENO} من {nbpg}
            </div>
        </div>
    </htmlpagefooter>
</body>

</html>
