import {defineConfig} from '@playwright/test';
export default defineConfig({testDir:'./tests',fullyParallel:false,workers:1,timeout:45000,use:{baseURL:process.env.TEST_BASE_URL||'http://localhost:5173',headless:true,screenshot:'only-on-failure',trace:'retain-on-failure'},reporter:'list'});
