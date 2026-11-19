const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
	testDir: 'specs',
	timeout: 300000,
	expect: { timeout: 30000 },
	retries: 0,
	workers: 1,
	reporter: [['list'], ['html', { open: 'never' }]],
	use: {
		baseURL: process.env.BASE_URL || 'http://localhost:8080',
		viewport: { width: 1366, height: 900 },
		screenshot: 'on',
		video: 'on',
		trace: 'retain-on-failure',
		actionTimeout: 60000,
		navigationTimeout: 60000
	}
});
