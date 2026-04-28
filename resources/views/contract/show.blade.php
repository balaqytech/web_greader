<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('admin.application.contract_title') ?? 'توقيع العقد' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
</head>
<body class="bg-gray-50 text-gray-900 font-sans antialiased p-4 md:p-8">
    <div class="max-w-4xl mx-auto bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        
        <div class="p-6 md:p-10">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-primary-600 mb-2">{{ $application->program->name }}</h1>
                <p class="text-gray-500">{{ __('admin.application.ref_no') }}: {{ $application->ref_no }} | {{ __('admin.student.name') }}: {{ $application->student_name }}</p>
            </div>

            <div class="prose prose-blue max-w-none mb-10 border p-6 rounded-lg bg-gray-50">
                {!! $application->program->contract !!}
            </div>

            <form id="sign-form" method="POST" action="{{ route('contract.sign', $application->contract_token) }}">
                @csrf
                <input type="hidden" name="signature" id="signature-input">
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('admin.application.draw_signature') ?? 'ارسم توقيعك هنا' }}</label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg overflow-hidden bg-white">
                        <canvas id="signature-pad" class="w-full h-64 touch-none"></canvas>
                    </div>
                    <div class="mt-2 flex justify-end">
                        <button type="button" id="clear-btn" class="text-sm text-red-600 hover:text-red-800">{{ __('admin.application.clear_signature') ?? 'مسح التوقيع' }}</button>
                    </div>
                </div>
                
                @error('signature')
                    <p class="text-red-500 text-sm mt-1 mb-4">{{ $message }}</p>
                @enderror

                <div class="flex justify-center">
                    <button type="button" id="submit-btn" class="px-8 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium transition-colors">
                        {{ __('admin.application.submit_signature') ?? 'اعتماد التوقيع' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const canvas = document.getElementById('signature-pad');
            const form = document.getElementById('sign-form');
            const signatureInput = document.getElementById('signature-input');
            const clearBtn = document.getElementById('clear-btn');
            const submitBtn = document.getElementById('submit-btn');

            function resizeCanvas() {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext("2d").scale(ratio, ratio);
            }

            window.onresize = resizeCanvas;
            resizeCanvas();

            const signaturePad = new SignaturePad(canvas, {
                backgroundColor: 'rgb(255, 255, 255)',
                penColor: 'rgb(0, 0, 0)'
            });

            clearBtn.addEventListener('click', function () {
                signaturePad.clear();
            });

            submitBtn.addEventListener('click', function () {
                if (signaturePad.isEmpty()) {
                    alert("{{ __('admin.application.signature_required') ?? 'الرجاء إدخال التوقيع' }}");
                    return;
                }
                
                const dataURL = signaturePad.toDataURL('image/png');
                signatureInput.value = dataURL;
                form.submit();
            });
        });
    </script>
</body>
</html>
