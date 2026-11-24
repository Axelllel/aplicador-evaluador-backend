import { defineConfig } from '@playwright/test';
export default defineConfig({
 testDir:'tests/browser',timeout:60000,workers:1,
 reporter:[['list'],['html',{open:'never'}]],
 use:{channel:process.env.CI?'chrome':undefined,baseURL:'http://127.0.0.1:8899',headless:true,permissions:['camera'],launchOptions:{args:['--use-fake-ui-for-media-stream','--use-fake-device-for-media-stream']},screenshot:'only-on-failure',trace:'retain-on-failure'},
 webServer:{command:'python3 -m http.server 8899 --bind 127.0.0.1 --directory public',url:'http://127.0.0.1:8899/qa-login.html',reuseExistingServer:false},
});
