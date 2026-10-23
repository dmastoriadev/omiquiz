// tailwind.config.js
import defaultTheme from 'tailwindcss/defaultTheme'
import forms from '@tailwindcss/forms'
import typography from '@tailwindcss/typography'

export default {
  content: [
    './resources/views/**/*.blade.php',
    './resources/js/**/*.js',
    './resources/**/*.vue',
    './storage/framework/views/*.php',
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
  ],
  theme: {
    container: {
      center: true,
      padding: { DEFAULT: '1rem', sm: '1.5rem', lg: '2rem' },
      screens: { '2xl': '1400px' },
    },
    extend: {
      colors: { brand: '#000000ff' },

      // ✅ Make Whitney the default sans stack
      fontFamily: {
        sans: ['Whitney', ...defaultTheme.fontFamily.sans],
      },

      // your existing prose tweaks
      typography: ({ theme }) => ({
        DEFAULT: {
          css: {
            h1: { fontWeight: '800' },
            h2: { fontWeight: '700' },
            h3: { fontWeight: '600' },
            'h1,h2,h3,h4': { color: theme('colors.slate.900') },
            a: { color: theme('colors.emerald.700'), fontWeight: 'inherit' },
            strong: { fontWeight: '700', color: 'inherit' },
            b:      { fontWeight: '700', color: 'inherit' },
            'a strong, a b, strong a, b a': { fontWeight: '700' },
          },
        },
      }),
    },
  },
  plugins: [forms, typography],
}
