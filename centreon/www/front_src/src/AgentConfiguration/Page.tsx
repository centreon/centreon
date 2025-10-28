import { DataTable, PageHeader, PageLayout } from '@centreon/ui/components';
import { useAtom, useSetAtom } from 'jotai';
import { useLayoutEffect } from 'react';
import { useTranslation } from 'react-i18next';
import AddModal from './Form/AddModal';
import UpdateModal from './Form/UpdateModal';
import ACListing from './Listing/Listing';
import { isWelcomePageDisplayedAtom, openFormModalAtom } from './atoms';
import { useGetAgentConfigurations } from './hooks/useGetAgentConfigurations';

import { LoadingSkeleton } from '@centreon/ui';
import { isNil, isNotEmpty } from 'ramda';

import useCountChangedFilters from './Listing/Actions/useCountChangedFilters';

import {
  labelAddAgentConfiguration,
  labelAgentsConfigurations,
  labelWelcomeDescription,
  labelWelcomeToTheAgentsConfigurationPage
} from './translatedLabels';

const WelcomePage = ({ labels, dataTestId, onCreate }) => {
  const { isLoading, data } = useGetAgentConfigurations();

  const setIsWelcomePageDisplayed = useSetAtom(isWelcomePageDisplayedAtom);

  const { isClear } = useCountChangedFilters();

  useLayoutEffect(() => {
    if (!isLoading && (!isClear || (isClear && isNotEmpty(data?.result)))) {
      setIsWelcomePageDisplayed(false);
    }
  }, [isLoading]);

  if (isLoading && isNil(data)) {
    return <LoadingSkeleton />;
  }

  return (
    <DataTable.EmptyState
      aria-label="create"
      data-testid={dataTestId}
      labels={labels}
      onCreate={onCreate}
    />
  );
};

const AgentConfigurationPage = (): JSX.Element => {
  const { t } = useTranslation();

  const [isWelcomePageDisplayed, setIsWelcomePageDisplayed] = useAtom(
    isWelcomePageDisplayedAtom
  );

  const { isLoading, data } = useGetAgentConfigurations();

  const setOpenFormModal = useSetAtom(openFormModalAtom);

  const openCreatetModal = (): void => {
    setOpenFormModal('add');

    setIsWelcomePageDisplayed(false);
  };

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
        <DataTable
          isEmpty={isWelcomePageDisplayed}
          variant={isWelcomePageDisplayed ? 'grid' : 'listing'}
        >
          {isWelcomePageDisplayed ? (
            <WelcomePage
              dataTestId="create-agent-configuration"
              labels={{
                title: t(labelWelcomeToTheAgentsConfigurationPage),
                description: t(labelWelcomeDescription),
                actions: {
                  create: t(labelAddAgentConfiguration)
                }
              }}
              onCreate={openCreatetModal}
            />
          ) : (
            <ACListing
              rows={data?.result}
              total={data?.meta.total}
              isLoading={isLoading}
            />
          )}
        </DataTable>
      </PageLayout.Body>
      <AddModal />
      <UpdateModal />
    </PageLayout>
  );
};

export default AgentConfigurationPage;
