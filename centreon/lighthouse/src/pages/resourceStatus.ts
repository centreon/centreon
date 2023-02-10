import { baseConfig, baseUrl } from '../defaults';

export const generateReportForResourceStatusPage = async ({
  flow,
  page,
}): Promise<void> => {
  await page.setCacheEnabled(false);

  await flow.navigate(`${baseUrl}monitoring/resources`, {
    name: 'Resource Status Cold navigation',
    ...baseConfig,
  });

  await page.setCacheEnabled(true);

  await flow.navigate(`${baseUrl}monitoring/resources`, {
    name: 'Resource Status Warm navigation',
    ...baseConfig,
  });

  await flow.snapshot({ name: 'Resource Status Snapshot', ...baseConfig });

  await page.waitForSelector('input[placeholder="Search"]');

  await flow.startTimespan({ name: 'Type search query', ...baseConfig });
  await page.type('input[placeholder="Search"]', 'Centreon');
  await flow.endTimespan();
};
