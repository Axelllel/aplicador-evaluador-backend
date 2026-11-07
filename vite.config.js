import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { bunny } from 'laravel-vite-plugin/fonts';
export default defineConfig({
  plugins: [laravel({input:['resources/css/app.css','resources/js/app.js','resources/css/student-entry.css','resources/js/student-entry.js','resources/css/student-ui.css','resources/js/student-ui.js'],refresh:true, fonts:[bunny('Instrument Sans',{weights:[400,500,600]})]}),tailwindcss()],
  server: {watch:{ignored:['**/storage/framework/views/**']}},
});
