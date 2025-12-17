import { LoadingSkeleton } from '@centreon/ui';
import { DataTable, PageHeader, PageLayout } from '@centreon/ui/components';

import { useAtom, useSetAtom } from 'jotai';
import { isNil, isNotEmpty, or } from 'ramda';
import { JSX, useLayoutEffect } from 'react';
import { useSearchParams } from 'react-router';

import { ConfigurationBase } from '../models';
import { modalStateAtom } from './atoms';
import { DeleteDialog, DuplicateDialog } from './Dialogs';
import useCoutChangedFilters from './Filters/AdvancedFilters/useCoutChangedFilters';
import { Listing } from './Listing';
import useLoadData from './Listing/useLoadData';
import { Modal } from './Modal';

const WelcomePage = ({
  hasWriteAccess,
  labels,
  dataTestId,
  onCreate,
  filtersAtom,
  filtersAtomKey,
  isWelcomePageDisplayedAtom
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
      data-testid={dataTestId}
      labels={labels}
      onCreate={onCreate}
      canCreate={hasWriteAccess}
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
  isWelcomePageDisplayedAtom
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
              labels={labels.welcomePage}
              onCreate={openCreatetModal}
              filtersAtom={filtersAtom}
              filtersAtomKey={filtersAtomKey}
              isWelcomePageDisplayedAtom={isWelcomePageDisplayedAtom}
              hasWriteAccess={!!actions?.edit}
            />
          ) : (
            <Listing<TFilters>
              selectedColumnIdsAtom={selectedColumnIdsAtom}
              columns={columns}
              hasWriteAccess={!!actions?.edit}
              actions={actions}
              isLoading={isLoading}
              data={data}
              filtersAtomKey={filtersAtomKey}
              filtersAtom={filtersAtom}
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
