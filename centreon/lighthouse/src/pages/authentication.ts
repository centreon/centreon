import { GenerateReportForPageProps } from '../models';
import { baseUrl } from '../defaults';

export const generateReportForAuthenticationPage = async ({
  navigate,
  snapshot,
  startTimespan,
  endTimespan,
  page,
}: GenerateReportForPageProps): Promise<void> => {
  await navigate({
    name: 'Authentication Cold navigation',
    url: `${baseUrl}administration/authentication`,
  });

  await navigate({
    name: 'Authentication Warm navigation',
    url: `${baseUrl}administration/authentication`,
  });

  await snapshot('Authentication Snapshot');

  await page.waitForSelector('input[aria-label="Minimum password length"]');

  await startTimespan('Change letter case');
  await page.click('button[aria-label="Password must contain lower case"]');
  await endTimespan();

  await page.click('button[aria-label="Password must contain lower case"]');

  await startTimespan('Change tab');
  await page.click('button[role="tab"]:nth-child(2)');
  await endTimespan();

  await page.click('div[aria-label="Identity provider"]');

  await startTimespan('Change OpenID connect activation');
  await page.click('input[aria-label="Enable OpenID Connect authentication"]');
  await endTimespan();
};
