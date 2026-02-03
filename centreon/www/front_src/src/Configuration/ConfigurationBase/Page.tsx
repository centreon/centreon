import { LoadingSkeleton } from '@centreon/ui';
import { DataTable, PageHeader, PageLayout } from '@centreon/ui/components';

import { useAtom, useSetAtom } from 'jotai';
import { isNil, isNotEmpty, or } from 'ramda';
import { JSX, useLayoutEffect } from 'react';
import { useSearchParams } from 'react-router';

import { ConfigurationBase } from '../models';
import { DeleteDialog, DuplicateDialog } from './Dialogs';
import useCoutChangedFilters from './Filters/AdvancedFilters/useCoutChangedFilters';
import { Listing } from './Listing';
import useLoadData from './Listing/useLoadData';
import { Modal } from './Modal';
import Navbar from './NavBar';
import { modalStateAtom } from './atoms';

const WelcomePage = ({
  labels,
  dataTestId,
  onCreate,
  filtersAtom,
  filtersAtomKey,
  isWelcomePageDisplayedAtom,
  hasWriteAccess
}) => {
  const { isLoading, data } = useLoadData({ filtersAtom, filtersAtomKey });

  const setIsWelcomePageDisplayed = useSetAtom(isWelcomePageDisplayedAtom);
  const { isClear } = useCoutChangedFilters({ filtersAtom });

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
      canCreate={hasWriteAccess}
      data-testid={dataTestId}
      labels={labels}
      onCreate={onCreate}
    />
  );
};

const Page = <TFilters,>({
  columns,
  resourceType,
  form,
  actions,
  labels,
  selectedColumnIdsAtom,
  filtersAtom,
  filtersAtomKey,
  isWelcomePageDisplayedAtom,
  navbar
}: Pick<
  ConfigurationBase<TFilters>,
  | 'columns'
  | 'form'
  | 'resourceType'
  | 'actions'
  | 'labels'
  | 'selectedColumnIdsAtom'
  | 'filtersAtom'
  | 'filtersAtomKey'
  | 'isWelcomePageDisplayedAtom'
  | 'navbar'
>): JSX.Element => {
  const [, setSearchParams] = useSearchParams();

  const setModalState = useSetAtom(modalStateAtom);
  const [isWelcomePageDisplayed, setIsWelcomePageDisplayed] = useAtom(
    isWelcomePageDisplayedAtom
  );

  const { isLoading, data } = useLoadData({ filtersAtom, filtersAtomKey });

  const openCreatetModal = (): void => {
    setSearchParams({ mode: 'add' });

    setModalState({ id: null, isOpen: true, mode: 'add' });

    setIsWelcomePageDisplayed(false);
  };

  return (
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Main>
            <PageHeader.Title title={labels.title} />
          </PageHeader.Main>
          {!!navbar && (
            <PageHeader.Actions>
              <Navbar navbar={navbar} />
            </PageHeader.Actions>
          )}
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <DataTable
          isEmpty={isWelcomePageDisplayed}
          variant={isWelcomePageDisplayed ? 'grid' : 'listing'}
        >
          {isWelcomePageDisplayed ? (
            <WelcomePage
              dataTestId={`create-${resourceType}`}
              filtersAtom={filtersAtom}
              filtersAtomKey={filtersAtomKey}
              hasWriteAccess={!!actions?.edit}
              isWelcomePageDisplayedAtom={isWelcomePageDisplayedAtom}
              labels={labels.welcomePage}
              onCreate={openCreatetModal}
            />
          ) : (
            <Listing<TFilters>
              actions={actions}
              columns={columns}
              data={data}
              filtersAtom={filtersAtom}
              filtersAtomKey={filtersAtomKey}
              hasWriteAccess={!!actions?.edit}
              isLoading={isLoading}
              selectedColumnIdsAtom={selectedColumnIdsAtom}
            />
          )}
        </DataTable>
      </PageLayout.Body>
      {or(!!actions?.edit, !!actions?.viewDetails) && (
        <Modal form={form} hasWriteAccess={!!actions?.edit} />
      )}
      {actions?.delete && <DeleteDialog />}
      {actions?.duplicate && <DuplicateDialog />}
    </PageLayout>
  );
};

export default Page;
