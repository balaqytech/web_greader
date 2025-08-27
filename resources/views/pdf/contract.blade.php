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
        }

        .header-logo {
            height: 100px;
        }

        .footer-container {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
        }

        .footer-logo {
            width: 100%;
            height: auto;
        }
    </style>
</head>

<body dir="rtl">
    <htmlpageheader name="page-header">
        <div class="header-container">
            <img src="{{ asset('logo.png') }}" class="header-logo" alt="Logo">
        </div>
    </htmlpageheader>

    <main>
        {!! $contract !!}
        <h2 class="text-center mt-6 mb-4">{{ __('frontend.signature') }}</h2>
        <img src="{{ asset($signature) }}" alt="" style="margin-top: 30px; max-width: 200px; max-height: 200px;">
    </main>

    <htmlpagefooter name="page-footer">
        <div class="footer-container">
            <img src="{{ asset('images/contract-footer.png') }}" class="footer-logo">
        </div>
    </htmlpagefooter>
</body>

</html>
