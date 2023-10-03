import { GenerateReportForPageProps } from '../models';
import { baseUrl } from '../defaults';
import { execSync } from 'child_process';
import fetch from 'node-fetch';
import panels from '../../fixtures/dashboardPanels.json'

export const generateReportForDashboardsPage = async ({
  navigate,
  snapshot,
  startTimespan,
  endTimespan,
  page,
}: GenerateReportForPageProps): Promise<void> => {
  execSync(`docker cp ./features.json centreon-dev:/usr/share/centreon/config/features.json`);

  const cookies = await page.cookies();

  await fetch(`${baseUrl}api/latest/configuration/dashboards`, {
    method: 'POST',
    body: JSON.stringify({
      name: "Dashboard",
      description: "Description",
    }),
    headers: {
      Cookie: `${cookies[0].name}=${cookies[0].value}`
    }
  });

  const response = await fetch(`${baseUrl}api/latest/configuration/dashboards/1`, {
    method: 'PATCH',
    body: JSON.stringify(panels),
    headers: {
      Cookie: `${cookies[0].name}=${cookies[0].value}`
    }
  });

  await navigate({
    name: 'Dashboards Cold navigation',
    url: `${baseUrl}home/dashboards`,
  });

  await navigate({
    name: 'Dashboards Warm navigation',
    url: `${baseUrl}home/dashboards`,
  });

  await snapshot('Dashboards Snapshot');

  await navigate({
    name: 'Dashboard Cold navigation',
    url: `${baseUrl}home/dashboards/1`,
  });

  await navigate({
    name: 'Dashboard Warm navigation',
    url: `${baseUrl}home/dashboards/1`,
  });

  await snapshot('Dashboard Snapshot');
};