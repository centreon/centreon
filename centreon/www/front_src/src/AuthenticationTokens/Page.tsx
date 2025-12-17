import { LoadingSkeleton } from '@centreon/ui';
import { DataTable, PageHeader, PageLayout } from '@centreon/ui/components';

import { useAtom, useSetAtom } from 'jotai';
import { isNil, isNotEmpty } from 'ramda';
import { useLayoutEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router';

import { isWelcomePageDisplayedAtom, modalStateAtom } from './atoms';
import { DeleteDialog, DisableDialog, EnableDialog } from './Dialogs';
import useCountChangedFilters from './Filters/useCountChangedFilters';
import { Listing } from './Listing';
import useLoadData from './Listing/useLoadData';
import Modal from './Modal';
import { TokenType } from './models';
import {
  labelAddToken,
  labelAuthenticationTokens,
  labelWelcomeDescription,
  labelWelcomePageTitle
} from './translatedLabels';

const WelcomePage = ({ labels, dataTestId, onCreate }) => {
  const { isLoading, data } = useLoadData();

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

const Page = (): JSX.Element => {
  const { t } = useTranslation();
  const [, setSearchParams] = useSearchParams();

  const [isWelcomePageDisplayed, setIsWelcomePageDisplayed] = useAtom(
    isWelcomePageDisplayedAtom
  );
  const setModalState = useSetAtom(modalStateAtom);

  const { isLoading, data } = useLoadData();

  const openCreatetModal = (): void => {
    setSearchParams({ mode: 'add', type: TokenType.API });

    setModalState({ isOpen: true, mode: 'add', type: TokenType.API });

    setIsWelcomePageDisplayed(false);
  };

  return (
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Main>
            <PageHeader.Title title={t(labelAuthenticationTokens)} />
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
              dataTestId="create-token"
              labels={{
                title: t(labelWelcomePageTitle),
                description: t(labelWelcomeDescription),
                actions: {
                  create: t(labelAddToken)
                }
              }}
              onCreate={openCreatetModal}
            />
          ) : (
            <Listing isLoading={isLoading} data={data} />
          )}
        </DataTable>
      </PageLayout.Body>
      <Modal />
      <DeleteDialog />
      <DisableDialog />
      <EnableDialog />
    </PageLayout>
  );
};

export default Page;
