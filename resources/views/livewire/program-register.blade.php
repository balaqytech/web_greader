<div>
    <section>
        <div class="wrapper relative min-h-[80vh]">
            <!-- Vimeo video embed -->
            <iframe src="https://player.vimeo.com/video/1086378824?title=0&byline=0&portrait=0&autoplay=1"
                class="absolute left-0 top-0 h-full w-full" frameborder="0"
                allow="autoplay; fullscreen; picture-in-picture" allowfullscreen>
            </iframe>
        </div>
    </section>

    <section class="py-24">
        <div class="wrapper prose prose-slate">
            <h1 class="text-center text-3xl font-bold text-gr-rose">
                سجل أطفالك معنا دون التزام
            </h1>
            <p class="text-center text-slate-700">
                تسجيل البيانات في هذه الاستئمارة لا يترتب عليه أي التزامات مالية أو قانونية.
                <br>
                هدف الاستئمارة أن تتمكن المدرسة من خدمتكم بسهولة ويسر.
            </p>

            <div
                class="flex items-center py-3 text-sm text-gr-green before:me-6 before:flex-1 before:border-t before:border-gray-200 after:ms-6 after:flex-1 after:border-t after:border-gray-200">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
            </div>

            <div class="mx-auto mt-12 list-none">
                <form wire:submit="create">
                    {{ $this->form }}
                </form>
            </div>
        </div>
    </section>
</div>
