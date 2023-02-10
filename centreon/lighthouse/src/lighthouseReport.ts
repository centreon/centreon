import fs from 'fs';

import puppeteer from 'puppeteer';
import { startFlow } from 'lighthouse';

import { generateReportForLoginPage } from './pages/login';
import { generateReportForResourceStatusPage } from './pages/resourceStatus';
import { generateReportForAuthenticationPage } from './pages/authentication';

const createReportFile = async (report): Promise<void> => {
  const lighthouseFolderExists = fs.existsSync('report');

  if (!lighthouseFolderExists) {
    fs.mkdirSync('report');
  }

  fs.writeFileSync('report/lighthouseci-index.html', await report);
};

const captureReport = async (): Promise<void> => {
  const browser = await puppeteer.launch({
    args: ['--lang=en-US,en', '--no-sandbox', '--disable-setuid-sandbox'],
    headless: true,
  });
  const page = await browser.newPage();

  const flow = await startFlow(page, {
    name: 'Centreon Web pages',
  });

  await generateReportForLoginPage({ flow, page });

  await generateReportForResourceStatusPage({ flow, page });

  await generateReportForAuthenticationPage({ flow, page });

  await browser.close();

  createReportFile(flow.generateReport());
};

captureReport();
