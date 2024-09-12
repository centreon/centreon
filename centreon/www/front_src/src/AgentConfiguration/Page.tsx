import { DataTable, PageHeader, PageLayout } from '@centreon/ui/components';
import { useTranslation } from 'react-i18next';
import { labelCreate } from '../Dashboards/translatedLabels';
import ACListing from './Listing/Listing';
import { useGetAgentConfigurations } from './hooks/useGetAgentConfigurations';
import {
  labelAgentsConfigurations,
  labelWelcomeToTheAgentsConfigurationPage
} from './translatedLabels';

const AgentConfigurationPage = (): JSX.Element => {
  const { t } = useTranslation();

  const { isDataEmpty, isLoading, total, data } = useGetAgentConfigurations();

  return (
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Title title={t(labelAgentsConfigurations)} />
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <DataTable isEmpty={isDataEmpty} variant="listing">
          {isDataEmpty && !isLoading ? (
            <DataTable.EmptyState
              aria-label="create"
              data-testid="create-agent-configuration"
              labels={{
                title: t(labelWelcomeToTheAgentsConfigurationPage),
                actions: {
                  create: t(labelCreate)
                }
              }}
              onCreate={() => undefined}
            />
          ) : (
            <ACListing rows={data} total={total} isLoading={isLoading} />
          )}
        </DataTable>
      </PageLayout.Body>
    </PageLayout>
  );
};

export default AgentConfigurationPage;
