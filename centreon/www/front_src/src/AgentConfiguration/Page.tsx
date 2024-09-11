import { DataTable, PageHeader, PageLayout } from '@centreon/ui/components';
import { useTranslation } from 'react-i18next';
import { labelCreate } from '../Dashboards/translatedLabels';
import { useGetAgentsConfigurations } from './hooks/useGetAgentsConfigurations';
import {
  labelAgentsConfigurations,
  labelWelcomeToTheAgentsConfigurationPage
} from './translatedLabels';

const AgentConfigurationPage = (): JSX.Element => {
  const { t } = useTranslation();

  const { isEmpty, isLoading } = useGetAgentsConfigurations();

  return (
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Title title={t(labelAgentsConfigurations)} />
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <DataTable isEmpty={isEmpty} variant="listing">
          {isEmpty && !isLoading && (
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
          )}
        </DataTable>
      </PageLayout.Body>
    </PageLayout>
  );
};

export default AgentConfigurationPage;
