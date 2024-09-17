import { PageSkeleton } from '@centreon/ui';
import { DataTable, PageHeader, PageLayout } from '@centreon/ui/components';
import { useSetAtom } from 'jotai';
import { isNil } from 'ramda';
import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import AddModal from './Form/AddModal';
import UpdateModal from './Form/UpdateModal';
import ACListing from './Listing/Listing';
import { openFormModalAtom } from './atoms';
import { useGetAgentConfigurations } from './hooks/useGetAgentConfigurations';
import {
  labelAddNewAgent,
  labelAgentsConfigurations,
  labelWelcomeToTheAgentsConfigurationPage
} from './translatedLabels';

const AgentConfigurationPage = (): JSX.Element => {
  const { t } = useTranslation();

  const { isDataEmpty, isLoading, total, data } = useGetAgentConfigurations();

  const setOpenFormModal = useSetAtom(openFormModalAtom);

  const add = useCallback(() => setOpenFormModal('add'), []);

  if (isLoading || isNil(data)) {
    return <PageSkeleton displayHeaderAndNavigation={false} />;
  }

  return (
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Title title={t(labelAgentsConfigurations)} />
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <DataTable
          isEmpty={isDataEmpty}
          variant={isDataEmpty ? 'grid' : 'listing'}
        >
          {isDataEmpty && !isLoading ? (
            <DataTable.EmptyState
              aria-label="create"
              data-testid="create-agent-configuration"
              labels={{
                title: t(labelWelcomeToTheAgentsConfigurationPage),
                actions: {
                  create: t(labelAddNewAgent)
                }
              }}
              onCreate={add}
            />
          ) : (
            <ACListing rows={data} total={total} isLoading={isLoading} />
          )}
        </DataTable>
      </PageLayout.Body>
      <AddModal />
      <UpdateModal />
    </PageLayout>
  );
};

export default AgentConfigurationPage;
