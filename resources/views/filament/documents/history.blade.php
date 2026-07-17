{{-- Newest-first version history for one document requirement. Downloads route through the
     authorized controller; rendering never mutates a row. --}}
<div class="space-y-3">
    @forelse ($files as $file)
        <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-gray-950 dark:text-white">
                    {{ $file->original_name }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $file->uploaded_at?->translatedFormat('Y-m-d H:i') }}
                    @if ($file->uploadedBy)
                        — {{ $file->uploadedBy->name ?? '' }}
                    @endif
                </p>
            </div>

            <a
                href="{{ route('application-documents.files.download', ['file' => $file->getKey()]) }}"
                target="_blank"
                rel="noopener"
                class="shrink-0 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
            >
                {{ __('admin.document.actions.download') }}
            </a>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('admin.document.messages.no_history') }}
        </p>
    @endforelse
</div>
