import { JSX } from 'react';

import { useSetAtom } from 'jotai';
import { isEmpty, or } from 'ramda';

import { DataTable, PageHeader, PageLayout } from '@centreon/ui/components';
import { Listing } from './Listing';
import { Modal } from './Modal';

import { useSearchParams } from 'react-router';

import { ConfigurationBase } from '../models';

import { DeleteDialog, DuplicateDialog } from './Dialogs';

import useCoutChangedFilters from './Filters/AdvancedFilters/useCoutChangedFilters';
import useLoadData from './Listing/useLoadData';
import { modalStateAtom } from './atoms';

const Page = ({
  columns,
  resourceType,
  form,
  actions,
  labels
}: Pick<
  ConfigurationBase,
  'columns' | 'form' | 'resourceType' | 'actions' | 'labels'
>): JSX.Element => {
  const [, setSearchParams] = useSearchParams();

  const setModalState = useSetAtom(modalStateAtom);

  const { isLoading, data } = useLoadData();

  const openCreatetModal = (): void => {
    setSearchParams({ mode: 'add' });

    setModalState({ id: null, isOpen: true, mode: 'add' });
  };

  const { isClear } = useCoutChangedFilters();

  const isWelcomePageDisplayed = isEmpty(data?.result) && isClear;

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
            <DataTable.EmptyState
              aria-label="create"
              data-testid={`create-${resourceType}`}
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
