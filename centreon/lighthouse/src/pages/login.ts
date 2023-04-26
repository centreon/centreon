import { GenerateReportForPageProps } from '../models';
import { baseUrl } from '../defaults';

export const generateReportForLoginPage = async ({
  navigate,
  snapshot,
  startTimespan,
  endTimespan,
  page,
}: GenerateReportForPageProps): Promise<void> => {
  await navigate({
    name: 'Login Cold navigation',
    url: `${baseUrl}login`,
  });

  await navigate({
    name: 'Login Warm navigation',
    url: `${baseUrl}login`,
  });

  await snapshot('Login Snapshot');

  await page.waitForSelector('input[aria-label="Alias"]');

  await startTimespan('Type alias');
  await page.type('input[aria-label="Alias"]', 'admin');
  await endTimespan();

  await startTimespan('Type password');
  await page.type('input[aria-label="Password"]', 'Centreon!2021');
  await endTimespan();

  await startTimespan('Click submit button');
  await page.click('button[aria-label="Connect"]');
  await endTimespan();
};
