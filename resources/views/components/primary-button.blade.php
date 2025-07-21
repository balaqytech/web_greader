<button
    {{ $attributes->merge(['class' => 'py-3 px-8 inline-flex items-center gap-x-2 text-sm font-bold rounded border border-transparent bg-gr-rose text-white hover:bg-gr-rose focus:outline-hidden focus:bg-gr-rose disabled:opacity-50 disabled:pointer-events-none']) }}>
    {{ $slot }}
</button>
