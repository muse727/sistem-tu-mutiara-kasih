import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

// Custom palette berdasarkan permintaan
const customGray = {
    50: '#F5F5F5',   // background aplikasi
    100: '#F5F5F5',  
    200: '#EDEDED',  // border / divider
    300: '#EDEDED',  
    400: '#777777',  // teks muted
    500: '#777777',  
    600: '#333333',  // teks sekunder
    700: '#333333',  
    800: '#171717',  // teks utama
    900: '#171717',  
    950: '#171717',
};

const customAccent = {
    50: '#fef0eb',
    100: '#fce4db',
    200: '#f9c4b3',
    300: '#f69e85',
    400: '#f37856',
    500: '#F56C3E',  // accent utama
    600: '#F56C3E',  // accent utama
    700: '#e45322',  
    800: '#be3e15',
    900: '#963316',
    950: '#963316',
};

export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        // OVERRIDE TOTAL: Hanya warna-warna ini yang akan dikenali Tailwind
        colors: {
            transparent: 'transparent',
            current: 'currentColor',
            white: '#FFFFFF',
            black: '#171717',

            // Netral
            gray: customGray,
            slate: customGray,
            zinc: customGray,
            neutral: customGray,
            stone: customGray,

            // Aksen (Apapun class warnanya, akan berubah jadi oranye ini)
            blue: customAccent,
            sky: customAccent,
            indigo: customAccent,
            cyan: customAccent,
            emerald: customAccent,
            green: customAccent,
            teal: customAccent,
            red: customAccent,
            rose: customAccent,
            pink: customAccent,
            orange: customAccent,
            amber: customAccent,
            yellow: customAccent,
            purple: customAccent,
            fuchsia: customAccent,
            violet: customAccent,
            lime: customAccent,
        },
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
