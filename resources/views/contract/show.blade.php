<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $applicationContract->application->program->name }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
</head>

<body class="bg-gray-50">

    <flux:container class="py-8">
        <flux:card class="max-w-4xl mx-auto">

            {{-- Header --}}
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-primary-600 mb-2">
                    {{ $applicationContract->application->program->name }}
                </h1>

                <p class="text-gray-500">
                    {{ __('admin.application.ref_no') }}:
                    {{ $applicationContract->application->ref_no }}
                    |
                    {{ __('admin.student.name') }}:
                    {{ $applicationContract->application->student_name }}
                </p>
            </div>

            {{-- Contract --}}
            <div class="prose max-w-none mb-10">
                {!! $contract !!}
            </div>

            {{-- Form --}}
            <form id="sign-form" method="POST"
                action="{{ route('contract.sign', $applicationContract->token) }}">
                @csrf

                <input type="hidden" name="signature" id="signature-input">

                {{-- Signature --}}
                <div class="mb-6">
                    <flux:label>
                        {{ __('admin.application.draw_signature') ?? 'ارسم توقيعك هنا' }}
                    </flux:label>

                    <div class="border-2 border-dashed border-gray-300 rounded-lg overflow-hidden bg-white mt-2">
                        <canvas id="signature-pad" class="w-full h-64 touch-none"></canvas>
                    </div>

                    <div class="mt-2 flex justify-end">
                        <flux:button variant="ghost" color="danger" id="clear-btn" type="button">
                            {{ __('admin.application.clear_signature') ?? 'مسح التوقيع' }}
                        </flux:button>
                    </div>
                </div>

                {{-- Error --}}
                @error('signature')
                    <flux:text class="text-red-600 mb-4">
                        {{ $message }}
                    </flux:text>
                @enderror

                {{-- Submit --}}
                <div class="flex justify-center">
                    <flux:button id="submit-btn" type="submit" variant="primary">
                        {{ __('admin.application.submit_signature') ?? 'اعتماد التوقيع' }}
                    </flux:button>
                </div>

            </form>

        </flux:card>
    </flux:container>

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

            clearBtn.addEventListener('click', () => signaturePad.clear());

            submitBtn.addEventListener('click', function () {
                if (signaturePad.isEmpty()) {
                    alert("{{ __('admin.application.signature_required') ?? 'الرجاء إدخال التوقيع' }}");
                    return;
                }

                signatureInput.value = signaturePad.toDataURL('image/png');
                form.submit();
            });
        });
    </script>

</body>
</html>