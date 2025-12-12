import puppeteer from 'puppeteer';

const url = process.argv[2];

if (!url) {
  console.error('TripIt URL is required.');
  process.exit(1);
}

const ACCEPT_TEXTS = [
  'accepter alle cookies',
  'accept all cookies',
  'godkend og fortsæt',
];

const normalize = (text) =>
  (text || '').toLowerCase().replace(/\s+/g, ' ').trim();

async function clickConsentButton(page) {
  const buttons = await page.$$('button');
  for (const button of buttons) {
    const text = normalize(await page.evaluate((el) => el.innerText, button));
    if (ACCEPT_TEXTS.some((phrase) => text.includes(phrase))) {
      await button.click();
      await page.waitForTimeout(500);
      return true;
    }
  }
  return false;
}

async function extractJson(page) {
  const data = await page.evaluate(() => {
    if (window.__NEXT_DATA__) {
      return window.__NEXT_DATA__;
    }
    if (window.__PRELOADED_STATE__) {
      return window.__PRELOADED_STATE__;
    }
    const script = document.querySelector('script#__NEXT_DATA__');
    if (script && script.textContent) {
      try {
        return JSON.parse(script.textContent);
      } catch (e) {
        return null;
      }
    }
    return null;
  });
  return data;
}

async function run() {
  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  try {
    const page = await browser.newPage();
    await page.setExtraHTTPHeaders({
      'Accept-Language': 'en-US,en;q=0.9',
    });
    await page.goto(url, { waitUntil: 'networkidle2', timeout: 60000 });

    await page.waitForTimeout(2000);
    await clickConsentButton(page);

    const payload = await extractJson(page);
    if (!payload) {
      console.error('TripIt JSON not found.');
      process.exit(2);
    }

    process.stdout.write(JSON.stringify(payload));
  } catch (error) {
    console.error(error.message || error.toString());
    process.exit(1);
  } finally {
    await browser.close();
  }
}

run();
