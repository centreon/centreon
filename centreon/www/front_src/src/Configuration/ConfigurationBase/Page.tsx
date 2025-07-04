import { isNil, isNotEmpty, or } from 'ramda';
import { JSX, useLayoutEffect } from 'react';

import { useAtom, useSetAtom } from 'jotai';

import { DataTable, PageHeader, PageLayout } from '@centreon/ui/components';
import { Listing } from './Listing';
import { Modal } from './Modal';

import { useSearchParams } from 'react-router';

import { ConfigurationBase } from '../Common/models';

import { DeleteDialog, DuplicateDialog } from './Dialogs';
import useCoutChangedFilters from './Filters/AdvancedFilters/useCoutChangedFilters';
import useLoadData from './Listing/useLoadData';
import { isWelcomePageDisplayedAtom, modalStateAtom } from './atoms';

import { LoadingSkeleton } from '@centreon/ui';

const WelcomePage = ({ labels, dataTestId, onCreate }) => {
  const { isLoading, data } = useLoadData();

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
      data-testid={dataTestId}
      labels={labels}
      onCreate={onCreate}
    />
  );
};

const Page = ({
  columns,
  resourceType,
  form,
  actions,
  labels,
  listAdditionalProps
}: Pick<
  ConfigurationBase,
  | 'columns'
  | 'form'
  | 'resourceType'
  | 'actions'
  | 'labels'
  | 'listAdditionalProps'
>): JSX.Element => {
  const [, setSearchParams] = useSearchParams();

  const setModalState = useSetAtom(modalStateAtom);
  const [isWelcomePageDisplayed, setIsWelcomePageDisplayed] = useAtom(
    isWelcomePageDisplayedAtom
  );

  const { isLoading, data } = useLoadData();

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
            />
          ) : (
            <Listing
              columns={columns}
              hasWriteAccess={!!actions?.edit}
              actions={actions}
              isLoading={isLoading}
              data={data}
              listAdditionalProps={listAdditionalProps}
            />
          )}
        </DataTable>
      </PageLayout.Body>
      {or(!!actions?.edit, !!actions?.viewDetails) && (
        <Modal form={form} hasWriteAccess={!!actions?.edit} />
      )}
      <DeleteDialog />
      <DuplicateDialog />
    </PageLayout>
  );
};

export default Page;
