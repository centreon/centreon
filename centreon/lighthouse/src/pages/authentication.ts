import { baseConfig, baseUrl } from '../defaults';

export const generateReportForAuthenticationPage = async ({
  flow,
  page,
}): Promise<void> => {
  await flow.navigate(`${baseUrl}administration/authentication`, {
    name: 'Authentication Cold navigation',
    ...baseConfig,
  });

  await flow.navigate(`${baseUrl}administration/authentication`, {
    name: 'Authentication Warm navigation',
    ...baseConfig,
  });

  await flow.snapshot({ name: 'Authentication Snapshot' });

  await page.waitForSelector('input[aria-label="Minimum password length"]');

  await flow.startTimespan({ name: 'Change letter case' });
  await page.click('button[aria-label="Password must contain lower case"]');
  await flow.endTimespan();

  await page.click('button[aria-label="Password must contain lower case"]');

  await flow.startTimespan({ name: 'Change tab' });
  await page.click('button[role="tab"]:nth-child(2)');
  await flow.endTimespan();

  await page.click('div[aria-label="Identity provider"]');

  await flow.startTimespan({ name: 'Change OpenID connect activation' });
  await page.click('input[aria-label="Enable OpenID Connect authentication"]');
  await flow.endTimespan();
};
