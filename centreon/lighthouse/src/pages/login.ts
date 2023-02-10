import { baseConfig, baseUrl } from '../defaults';

export const generateReportForLoginPage = async ({
  flow,
  page,
}): Promise<void> => {
  await flow.navigate(`${baseUrl}login`, {
    name: 'Login Cold navigation',
    ...baseConfig,
  });

  await flow.navigate(`${baseUrl}login`, {
    name: 'Login Warm navigation',
    ...baseConfig,
  });

  await flow.snapshot({ name: 'Login Snapshot', ...baseConfig });

  await page.waitForSelector('input[aria-label="Alias"]');

  await flow.startTimespan({ name: 'Type alias', ...baseConfig });
  await page.type('input[aria-label="Alias"]', 'admin');
  await flow.endTimespan();

  await flow.startTimespan({ name: 'Type password', ...baseConfig });
  await page.type('input[aria-label="Password"]', 'Centreon!2021');
  await flow.endTimespan();

  await flow.startTimespan({ name: 'Click submit button', ...baseConfig });
  await page.click('button[aria-label="Connect"]');
  await flow.endTimespan();
};
