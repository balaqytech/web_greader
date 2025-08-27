<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? env('APP_NAME') }}</title>
    
    <!-- Email-safe inline styles -->
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@100;200;300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Reset styles for email clients */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            font-family: 'IBM Plex Sans Arabic', sans-serif;
        }
        
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }
        
        /* Base styles */
        body {
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            font-family: 'IBM Plex Sans Arabic', sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #475569;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .header {
            background-color: #df3889;
            padding: 32px 24px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0 0 8px 0;
            font-size: 28px;
            font-weight: bold;
            color: #ffffff;
            line-height: 1.2;
        }
        
        .header p {
            margin: 0;
            font-size: 18px;
            color: #ffffff;
            opacity: 0.9;
        }
        
        .content {
            padding: 32px 24px;
        }
        
        .greeting {
            margin-bottom: 24px;
        }
        
        .greeting h2 {
            margin: 0 0 12px 0;
            font-size: 24px;
            font-weight: 600;
            color: #1e293b;
            line-height: 1.3;
        }
        
        .greeting p {
            margin: 0;
            color: #475569;
            line-height: 1.6;
        }
        
        .greeting strong {
            color: #1e293b;
        }
        
        .details-card {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }
        
        .details-card h3 {
            margin: 0 0 16px 0;
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
        }
        
        .details-card .icon {
            width: 20px;
            height: 20px;
            margin-right: 8px;
            color: #df3889;
        }
        
        .details-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }
        
        .details-grid .detail-item {
            display: table-cell;
            width: 50%;
            padding: 8px 0;
            vertical-align: top;
        }
        
        .detail-item .label {
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
            margin-bottom: 4px;
            display: block;
        }
        
        .detail-item .value {
            color: #1e293b;
            font-weight: 500;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            background-color: #fef3c7;
            color: #92400e;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .contact-section {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .contact-section p {
            margin: 0 0 16px 0;
            color: #475569;
        }
        
        .button-container {
            text-align: center;
        }
        
        .primary-button {
            display: inline-block;
            background-color: #df3889;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 16px;
            border: none;
            cursor: pointer;
        }
        
        .primary-button:hover {
            background-color: #be185d;
        }
        
        .footer {
            background-color: #f8fafc;
            padding: 24px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        
        .footer p {
            margin: 0 0 8px 0;
            font-size: 14px;
            color: #64748b;
        }
        
        .footer p:last-child {
            margin-bottom: 0;
        }
        
        /* RTL support */
        [dir="rtl"] .details-grid .detail-item {
            text-align: right;
        }
        
        [dir="rtl"] .details-card .icon {
            margin-right: 0;
            margin-left: 8px;
        }
        
        /* Responsive design */
        @media only screen and (max-width: 600px) {
            .email-container {
                margin: 0;
                border-radius: 0;
            }
            
            .header, .content, .footer {
                padding: 24px 16px;
            }
            
            .details-grid .detail-item {
                display: block;
                width: 100%;
                margin-bottom: 16px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .header p {
                font-size: 16px;
            }
        }
    </style>
</head>

<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <h1>مرحبا في مدرسة القارئ العبقري</h1>
            <p>تم تسجيلك بنجاح</p>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Greeting -->
            <div class="greeting">
                <h2>مرحبا {{ $enrollment->student->parentAccount->name }}!</h2>
                <p>
                    شكرا لتسجيلك في <strong>{{ $enrollment->program->name }}</strong>.
                    نحن نشعر بالعزيمة لديك جمعنا في مدرستنا ونتطلع إلى دعم رحلتك التعليمية.
                </p>
            </div>

            <!-- Registration Details Card -->
            <div class="details-card">
                <h3>
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    تفاصيل التسجيل
                </h3>
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="label">البرنامج</span>
                        <div class="value">{{ $enrollment->program->name }}</div>
                    </div>
                    <div class="detail-item">
                        <span class="label">تاريخ التسجيل</span>
                        <div class="value">{{ $enrollment->created_at->format('F j, Y') }}</div>
                    </div>
                    <div class="detail-item">
                        <span class="label">البريد الإلكتروني لولي الأمر</span>
                        <div class="value">{{ $enrollment->student->parentAccount->email }}</div>
                    </div>
                    <div class="detail-item">
                        <span class="label">الحالة</span>
                        <div class="value">
                            <span class="status-badge">{{ $enrollment->status->getLabel() }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="contact-section">
                <p>
                    الآن نرجو منك الاطلاع على العقد وتوقيعه عبر الرابط التالي:
                </p>
                <div class="button-container">
                    <a href="{{ route('sign-contract', $enrollment->id) }}" class="primary-button" target="_blank">
                        العقد
                    </a>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>شكرا لتسجيلك في مدرسة القارئ العبقري</p>
            <p>&copy; {{ date('Y') }} مدرسة القارئ العبقري. جميع الحقوق محفوظة.</p>
        </div>
    </div>
</body>
</html>