import fs from 'fs';

import puppeteer from 'puppeteer';
import { startFlow } from 'lighthouse';

import { generateReportForLoginPage } from './pages/login';
import { generateReportForResourceStatusPage } from './pages/resourceStatus';
import { generateReportForAuthenticationPage } from './pages/authentication';
import { baseConfig } from './defaults';
import type { NavigateProps } from './models';

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

  const navigate = async ({ url, name }: NavigateProps): Promise<void> => {
    await flow.navigate(url, { formFactor: 'desktop', name, ...baseConfig });
  };

  const snapshot = async (name: string): Promise<void> => {
    await flow.snapshot({ formFactor: 'desktop', name, ...baseConfig });
  };

  const startTimespan = async (name: string): Promise<void> => {
    await flow.startTimespan({ formFactor: 'desktop', name, ...baseConfig });
  };

  const endTimespan = async (): Promise<void> => {
    await flow.endTimespan();
  };

  await generateReportForLoginPage({
    endTimespan,
    navigate,
    page,
    snapshot,
    startTimespan,
  });

  await generateReportForResourceStatusPage({
    endTimespan,
    navigate,
    page,
    snapshot,
    startTimespan,
  });

  await generateReportForAuthenticationPage({
    endTimespan,
    navigate,
    page,
    snapshot,
    startTimespan,
  });

  await browser.close();

  createReportFile(flow.generateReport());
};

captureReport();
