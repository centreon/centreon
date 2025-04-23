import { baseUrl } from '../defaults';
import additionalConnectors from '../fixtures/additionalConnectors';
import { GenerateReportForPageProps } from '../models';

export const generateReportForACCsPage = async ({
  navigate,
  snapshot,
  page
}: GenerateReportForPageProps): Promise<void> => {
  const cookies = await page.cookies();

  await fetch(
    `${baseUrl}api/latest/configuration/additional-connector-configurations`,
    {
      method: 'POST',
      body: JSON.stringify(additionalConnectors),
      headers: {
        Cookie: `${cookies[0].name}=${cookies[0].value}`
      }
    }
  );

  await page.setCacheEnabled(false);

  await navigate({
    name: 'ACCs page Cold navigation',
    url: `${baseUrl}configuration/additional-connector-configurations`
  });

  await page.setCacheEnabled(true);

  await navigate({
    name: 'ACCs page Warm navigation',
    url: `${baseUrl}configuration/additional-connector-configurations`
  });

  await snapshot('ACCs Snapshot');
};
