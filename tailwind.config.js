const defaultTheme = require('tailwindcss/defaultTheme')

import preset from './vendor/filament/support/tailwind.config.preset'

export default {
    presets: [preset],
    content: [
        "./resources/**/*.blade.php",
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                'sans': ['"expo"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                'gr-green': '#bcd04a',
                'gr-blue': '#47abda',
                'gr-orange': '#f16543',
                'gr-rose': '#df3889',
            },
        },
    },
}
