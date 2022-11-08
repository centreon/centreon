import fs from 'fs';

import puppeteer from 'puppeteer';
import { startFlow } from 'lighthouse/lighthouse-core/fraggle-rock/api';

import { generateReportForLoginPage } from './pages/login';
import { generateReportForResourceStatusPage } from './pages/resourceStatus';
import { baseConfigContext } from './defaults';
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
    args: [
      '--lang=en-US,en',
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-gpu',
      '--disable-dev-shm-usage',
      '--no-first-run',
    ],
    headless: true,
  });
  const page = await browser.newPage();

  const flow = await startFlow(page, {
    configContext: baseConfigContext,
    name: 'Centreon Web pages',
  });

  await generateReportForLoginPage({ flow, page });

  await generateReportForResourceStatusPage({ flow, page });

  await generateReportForAuthenticationPage({ flow, page });

  await browser.close();

  await createReportFile(flow.generateReport());
};

captureReport();
