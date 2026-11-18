const path = require('path');
const { test, expect } = require('@playwright/test');

const PACKAGE = path.resolve(process.env.FRENET_PACKAGE || 'frenet.ocmod.zip');
const PREVIOUS_PACKAGE = process.env.FRENET_PREVIOUS_PACKAGE ? path.resolve(process.env.FRENET_PREVIOUS_PACKAGE) : '';
const TOKEN = 'e2e-token';
const ORIGIN = '01310-100';
const PRODUCT_ID = 40;
const ADDRESS = { address: 'Avenida Rio Branco, 1', city: 'Rio de Janeiro', postcode: '20040-020', zone: 'Rio de Janeiro' };

const login = async (page) => {
	await page.goto('admin/', { waitUntil: 'networkidle' });
	await page.fill('input[name=username]', 'admin');
	await page.fill('input[name=password]', 'admin');
	await Promise.all([page.waitForURL(/user_token=/), page.click('button[type=submit]')]);

	return new URL(page.url()).searchParams.get('user_token');
};

const upload = async (page, token, file) => {
	await page.goto(`admin/index.php?route=marketplace/installer&user_token=${token}`, { waitUntil: 'networkidle' });
	await page.click('#button-upload');
	await page.setInputFiles('#form-upload input[name=file]', file);
	await page.waitForFunction(() => /text-(success|danger)/.test((document.querySelector('#progress-text') || {}).innerHTML || ''), null, { timeout: 120000 });
	await expect(page.locator('#progress-text .text-danger')).toHaveCount(0);
	await expect(page.locator('#progress-text .text-success')).toBeVisible();
};

const frenetRow = async (page, token) => {
	await page.goto(`admin/index.php?route=marketplace/extension&type=shipping&user_token=${token}`, { waitUntil: 'networkidle' });
	await page.waitForSelector('#extension table');

	return page.locator('#extension tr', { hasText: 'Frenet' });
};

const installShipping = async (page, token) => {
	const row = await frenetRow(page, token);

	await expect(row).toHaveCount(1);
	await Promise.all([page.waitForResponse(/extension\/extension\/shipping\/install/), row.locator('a.btn-success').click()]);
	await expect(page.locator('#extension tr', { hasText: 'Frenet' }).locator('a.btn-danger')).toBeVisible();
};

const openSettings = async (page, token) => {
	const row = await frenetRow(page, token);

	await Promise.all([page.waitForURL(/route=extension\/shipping\/frenet/), row.locator('a.btn-primary').click()]);
	await page.waitForLoadState('networkidle');
};

const saveSettings = async (page, token) => {
	await page.fill('#input-token', TOKEN);
	await page.fill('#input-postcode', ORIGIN);
	await page.selectOption('#input-status', '1');
	await Promise.all([page.waitForURL(/marketplace\/extension/), page.click('button[form=form-shipping]')]);
	await expect(page.locator('.alert-success')).toBeVisible();
	await page.goto(`admin/index.php?route=extension/shipping/frenet&user_token=${token}`, { waitUntil: 'networkidle' });
	await expect(page.locator('#input-postcode')).toHaveValue(ORIGIN);
	await expect(page.locator('#input-status')).toHaveValue('1');
	await expect(page.locator('#frenet-migrated')).toHaveCount(0);
};

const simulate = async (page) => {
	await page.goto(`index.php?route=product/product&product_id=${PRODUCT_ID}`, { waitUntil: 'networkidle' });
	await page.fill('#input-frenet-postcode', ADDRESS.postcode);
	await page.click('#button-frenet-simulator');

	const rows = page.locator('#frenet-simulator-result tr');

	await expect(rows).toHaveCount(2);
	await expect(rows.nth(0)).toContainText('PAC');
	await expect(rows.nth(1)).toContainText('SEDEX');
};

const order = async (page, testInfo) => {
	await page.request.post('index.php?route=checkout/cart/add', { form: { product_id: String(PRODUCT_ID), quantity: '1' } });
	await page.goto('index.php?route=checkout/checkout', { waitUntil: 'networkidle' });
	await page.locator('#collapse-checkout-option input[value=guest]').check();
	await page.click('#button-account');
	await page.waitForSelector('#input-payment-firstname', { state: 'visible' });
	await page.fill('#input-payment-firstname', 'Teste');
	await page.fill('#input-payment-lastname', 'Frenet');
	await page.fill('#input-payment-email', 'e2e@example.com');
	await page.fill('#input-payment-telephone', '21999999999');
	await page.fill('#input-payment-address-1', ADDRESS.address);
	await page.fill('#input-payment-city', ADDRESS.city);
	await page.fill('#input-payment-postcode', ADDRESS.postcode);
	await page.selectOption('#input-payment-country', { label: 'Brazil' });
	await page.waitForFunction(() => document.querySelectorAll('#input-payment-zone option').length > 2);

	for (let attempt = 0; attempt < 5; attempt++) {
		await page.selectOption('#input-payment-zone', { label: ADDRESS.zone });
		await page.waitForLoadState('networkidle');

		if (await page.inputValue('#input-payment-zone')) break;
	}

	await page.click('#button-guest');

	const rates = page.locator('#collapse-shipping-method input[value^="frenet."]');

	await expect(rates).toHaveCount(2, { timeout: 90000 });
	await rates.first().check();

	const title = (await rates.first().locator('xpath=..').innerText()).replace(/\s+/g, ' ').trim().replace(/\s*-\s*[^-]*$/, '');

	await page.screenshot({ path: testInfo.outputPath('checkout-shipping.png'), fullPage: true });
	await page.click('#button-shipping-method');
	await page.locator('#collapse-payment-method input[value=cod]').check();

	const agree = page.locator('#collapse-payment-method input[name=agree]');

	if (await agree.count()) await agree.check();
	await page.click('#button-payment-method');
	await expect(page.locator('#collapse-checkout-confirm')).toContainText(title);
	await Promise.all([page.waitForURL(/checkout\/success/, { timeout: 60000 }), page.click('#button-confirm')]);

	return title;
};

const checkOrder = async (page, token, title, testInfo) => {
	await page.goto(`admin/index.php?route=sale/order/info&order_id=1&user_token=${token}`, { waitUntil: 'networkidle' });
	await expect(page.locator('body')).toContainText(title);
	await page.screenshot({ path: testInfo.outputPath('admin-order.png'), fullPage: true });
};

const watch = async (page, testInfo) => {
	const errors = [];

	await page.context().addCookies([{ name: 'language', value: 'en-gb', url: testInfo.project.use.baseURL }]);

	page.on('pageerror', (error) => errors.push(`js: ${error.message}`));
	page.on('response', (response) => {
		if (response.status() >= 500) errors.push(`${response.status()} ${response.url()}`);
	});

	return errors;
};

test('installs the package, quotes on the product page and at checkout, and places an order with Frenet shipping', async ({ page }, testInfo) => {
	test.skip(PREVIOUS_PACKAGE !== '', 'upgrade run');

	const errors = await watch(page, testInfo);
	const token = await login(page);
	let title = '';

	await test.step('Extensions > Installer uploads frenet.ocmod.zip', () => upload(page, token, PACKAGE));
	await test.step('Extensions > Extensions > Shipping installs Frenet', () => installShipping(page, token));
	await test.step('Frenet settings save the token, origin postcode and status', async () => {
		await openSettings(page, token);
		await saveSettings(page, token);
	});
	await test.step('Product page simulator lists the Frenet services', () => simulate(page));
	await test.step('Guest checkout quotes Frenet and places the order', async () => {
		title = await order(page, testInfo);
	});
	await test.step('The order keeps the Frenet shipping', () => checkOrder(page, token, title, testInfo));

	expect(errors).toEqual([]);
});

test('upgrades the published module with key and password, saves the token, quotes and places an order with Frenet shipping', async ({ page }, testInfo) => {
	test.skip(PREVIOUS_PACKAGE === '', 'install run');

	const errors = await watch(page, testInfo);
	const token = await login(page);
	let title = '';

	await test.step('The published module is installed with key and password', async () => {
		await upload(page, token, PREVIOUS_PACKAGE);
		await installShipping(page, token);
		await openSettings(page, token);
		await page.fill('#input-contrato-codigo', 'e2e-key');
		await page.fill('#input-contrato-senha', 'e2e-password');
		await page.fill('#input-postcode', '01310100');
		await page.selectOption('#input-status', '1');
		await Promise.all([page.waitForURL(/marketplace\/extension/), page.click('button[form=form-frenet]')]);
		await expect(page.locator('.alert-success')).toBeVisible();
	});
	await test.step('Extensions > Installer uploads frenet.ocmod.zip over it', () => upload(page, token, PACKAGE));
	await test.step('Frenet settings warn that key and password were removed and save the token', async () => {
		await openSettings(page, token);
		await expect(page.locator('#frenet-migrated')).toBeVisible();
		await expect(page.locator('#input-token')).toHaveValue('');
		await page.screenshot({ path: testInfo.outputPath('admin-migrated.png'), fullPage: true });
		await saveSettings(page, token);
	});
	await test.step('Product page simulator lists the Frenet services', () => simulate(page));
	await test.step('Guest checkout quotes Frenet and places the order', async () => {
		title = await order(page, testInfo);
	});
	await test.step('The order keeps the Frenet shipping', () => checkOrder(page, token, title, testInfo));

	expect(errors).toEqual([]);
});
