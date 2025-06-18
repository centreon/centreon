import { baseUrl } from '../defaults';
import agentConfiguration from '../fixtures/agentConfiguration';
import { GenerateReportForPageProps } from '../models';

export const generateReportForAgentConfigurationPage = async ({
  navigate,
  snapshot,
  page
}: GenerateReportForPageProps): Promise<void> => {
  const cookies = await page.cookies();

  await fetch(`${baseUrl}api/latest/configuration/agent-configuration`, {
    method: 'POST',
    body: JSON.stringify(agentConfiguration),
    headers: {
      Cookie: `${cookies[0].name}=${cookies[0].value}`
    }
  });

  await page.setCacheEnabled(false);

  await navigate({
    name: 'Agent configuration page Cold navigation',
    url: `${baseUrl}configuration/pollers/agent-configurations`
  });

  await page.setCacheEnabled(true);

  await navigate({
    name: 'Agent configuration page Warm navigation',
    url: `${baseUrl}configuration/pollers/agent-configurations`
  });

  await snapshot('Agent configuration Snapshot');
};
