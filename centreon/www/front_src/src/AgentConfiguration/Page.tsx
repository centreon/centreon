import { LoadingSkeleton } from '@centreon/ui';
import { DataTable, PageHeader, PageLayout } from '@centreon/ui/components';
import { useAtom, useSetAtom } from 'jotai';
import { JSX, useLayoutEffect } from 'react';
import { useTranslation } from 'react-i18next';
import AddModal from './Form/AddModal';
import UpdateModal from './Form/UpdateModal';
import ACListing from './Listing/Listing';
import { isWelcomePageDisplayedAtom, openFormModalAtom } from './atoms';
import useCoutChangedFilters from './hooks/useCoutChangedFilters';
import { useGetAgentConfigurations } from './hooks/useGetAgentConfigurations';

import { isNil, isNotEmpty } from 'ramda';
import {
  labelAddAgentConfiguration,
  labelAgentsConfigurations,
  labelWelcomeDescription,
  labelWelcomeToTheAgentsConfigurationPage
} from './translatedLabels';

const WelcomePage = ({ labels, onCreate }) => {
  const { isLoading, data } = useGetAgentConfigurations();

  const setIsWelcomePageDisplayed = useSetAtom(isWelcomePageDisplayedAtom);
  const { isClear } = useCoutChangedFilters();

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
      buttonCreateTestId="create-agent-configuration"
      data-testid={'create-agent-configuration'}
      labels={labels}
      onCreate={onCreate}
    />
  );
};

const AgentConfigurationPage = (): JSX.Element => {
  const { t } = useTranslation();

  const { isLoading, total, data } = useGetAgentConfigurations();

  const setOpenFormModal = useSetAtom(openFormModalAtom);

  const [isWelcomePageDisplayed, setIsWelcomePageDisplayed] = useAtom(
    isWelcomePageDisplayedAtom
  );

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
              total={total}
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
