import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
export default defineConfig({
  plugins: [laravel({input:['resources/js/password-recovery.js','resources/css/ui-standards.css','resources/css/app.css','resources/js/app.js','resources/css/student-entry.css','resources/js/student-entry.js','resources/css/student-ui.css','resources/js/student-ui.js'],refresh:true}),tailwindcss()],
  server: {watch:{ignored:['**/storage/framework/views/**']}},
});
