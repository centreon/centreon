import { PageSkeleton } from '@centreon/ui';
import { DataTable, PageHeader, PageLayout } from '@centreon/ui/components';
import { useAtomValue, useSetAtom } from 'jotai';
import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import AddModal from './Form/AddModal';
import UpdateModal from './Form/UpdateModal';
import ACListing from './Listing/Listing';
import { openFormModalAtom, searchAtom } from './atoms';
import { useGetAgentConfigurations } from './hooks/useGetAgentConfigurations';
import {
  labelAddAgentConfiguration,
  labelAgentsConfigurations,
  labelWelcomeDescription,
  labelWelcomeToTheAgentsConfigurationPage
} from './translatedLabels';

const AgentConfigurationPage = (): JSX.Element => {
  const { t } = useTranslation();

  const search = useAtomValue(searchAtom);

  const { isDataEmpty, isLoading, hasData, total, data } =
    useGetAgentConfigurations();

  const setOpenFormModal = useSetAtom(openFormModalAtom);

  const add = useCallback(() => setOpenFormModal('add'), []);

  if (isLoading && !hasData) {
    return <PageSkeleton displayHeaderAndNavigation={false} />;
  }

  const isEmpty = isDataEmpty && !isLoading && !search;

  return (
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Main>
            <PageHeader.Title title={t(labelAgentsConfigurations)} />
          </PageHeader.Main>
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <DataTable isEmpty={isEmpty} variant={isEmpty ? 'grid' : 'listing'}>
          {isEmpty ? (
            <DataTable.EmptyState
              aria-label="create"
              buttonCreateTestId="create-agent-configuration"
              labels={{
                title: t(labelWelcomeToTheAgentsConfigurationPage),
                description: t(labelWelcomeDescription),
                actions: {
                  create: t(labelAddAgentConfiguration)
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
