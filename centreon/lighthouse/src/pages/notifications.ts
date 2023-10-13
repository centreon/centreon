import { GenerateReportForPageProps } from '../models';
import { baseUrl } from '../defaults';
import notification from '../fixtures/notification';

export const generateReportForNotificationsPage = async ({
  navigate,
  snapshot,
  page,
}: GenerateReportForPageProps): Promise<void> => {
  const cookies = await page.cookies();

  await fetch(`${baseUrl}api/latest/configuration/notifications`, {
    method: 'POST',
    body: JSON.stringify(notification),
    headers: {
      Cookie: `${cookies[0].name}=${cookies[0].value}`
    }
  });

  await navigate({
    name: 'Notifications Cold navigation',
    url: `${baseUrl}configuration/notifications`,
  });

  await navigate({
    name: 'Notifications Warm navigation',
    url: `${baseUrl}configuration/notifications`,
  });

  await snapshot('Notifications Snapshot');
};