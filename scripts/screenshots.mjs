// Development only: captures the README screenshots from a running local Moodle
// with the demo course (`make up && make demo`).
//
//   npm install --no-save --prefix /tmp/pptr puppeteer-core
//   NODE_PATH=/tmp/pptr/node_modules MOODLE_URL=http://localhost CHROME_PATH=/path/to/chrome \
//       node scripts/screenshots.mjs
//
// Writes docs/screenshots/*.png and docs/screenshots/course-export.pdf (the
// course PDF downloaded as the demo student). PDF pages are rendered to PNG
// separately with poppler: see DEVELOPMENT.md.
import fs from 'node:fs';
import path from 'node:path';
import {createRequire} from 'node:module';

const require = createRequire(import.meta.url);
const puppeteer = require('puppeteer-core');

const base = (process.env.MOODLE_URL || 'http://localhost').replace(/\/$/, '');
const out = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..', 'docs', 'screenshots');
const password = process.env.DEMO_PASSWORD || 'Demo!2026';
fs.mkdirSync(out, {recursive: true});

const browser = await puppeteer.launch({
    executablePath: process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    headless: true,
    defaultViewport: {width: 1366, height: 900, deviceScaleFactor: 1},
});

/**
 * Logs in as a demo user in a fresh browser context and returns the page.
 *
 * @param {string} username Demo user name.
 * @returns {Promise<import('puppeteer-core').Page>}
 */
async function login(username) {
    const context = await browser.createBrowserContext();
    const page = await context.newPage();
    await page.goto(`${base}/login/index.php`, {waitUntil: 'networkidle2'});
    await page.type('#username', username);
    await page.type('#password', password);
    await Promise.all([page.waitForNavigation({waitUntil: 'networkidle2'}), page.click('#loginbtn')]);
    return page;
}

/**
 * Opens the demo course with the block drawer visible.
 *
 * @param {import('puppeteer-core').Page} page Logged-in page.
 * @returns {Promise<void>}
 */
async function openCourse(page) {
    await page.goto(`${base}/course/view.php?name=PDFDEMO`, {waitUntil: 'networkidle2'});
    const opener = await page.$('[data-toggler="drawers"][data-target="theme_boost-drawers-blocks"][data-action="opendrawer"]');
    if (opener && await opener.isVisible()) {
        await opener.click();
        await new Promise((resolve) => setTimeout(resolve, 600));
    }
}

const student = await login('student_demo');
await openCourse(student);
await student.screenshot({path: path.join(out, 'course-page-student.png')});
const block = await student.$('section.block_pdfexport');
await block.screenshot({path: path.join(out, 'block-published-list.png')});

// Download the course PDF and one resource PDF with the student's session.
const links = await student.$$eval('section.block_pdfexport a', (anchors) => anchors.map((a) => a.href));
const download = async (url, file) => {
    const bytes = await student.evaluate(async (href) => {
        const response = await fetch(href, {credentials: 'same-origin'});
        return Array.from(new Uint8Array(await response.arrayBuffer()));
    }, url);
    fs.writeFileSync(path.join(out, file), Buffer.from(bytes));
};
await download(links.find((href) => !href.includes('cmid=') && href.includes('download.php')), 'course-export.pdf');
await download(links.find((href) => href.includes('cmid=')), 'resource-export.pdf');

const teacher = await login('teacher_demo');
await openCourse(teacher);
const manage = await teacher.$eval('section.block_pdfexport a[href*="manage.php"]', (a) => a.href);
await teacher.goto(manage, {waitUntil: 'networkidle2'});
await teacher.screenshot({path: path.join(out, 'teacher-selection.png'), fullPage: true});

await browser.close();
console.log(`Screenshots written to ${out}`);
