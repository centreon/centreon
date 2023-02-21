import { GenerateReportForPageProps } from '../models';
import { baseUrl } from '../defaults';

export const generateReportForResourceStatusPage = async ({
  navigate,
  snapshot,
  startTimespan,
  endTimespan,
  page,
}: GenerateReportForPageProps): Promise<void> => {
  await page.setCacheEnabled(false);

  await navigate({
    name: 'Resource Status Cold navigation',
    url: `${baseUrl}monitoring/resources`,
  });

  await page.setCacheEnabled(true);

  await navigate({
    name: 'Resource Status Warm navigation',
    url: `${baseUrl}monitoring/resources`,
  });

  await snapshot('Resource Status Snapshot');

  await page.waitForSelector('input[placeholder="Search"]');

  await startTimespan('Type search query');
  await page.type('input[placeholder="Search"]', 'Centreon');
  await endTimespan();
};
