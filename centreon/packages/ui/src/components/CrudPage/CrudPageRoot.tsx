import { useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useCallback, useRef } from 'react';

import PageSkeleton from '../../PageSkeleton';
import { DataTable } from '../DataTable';
import { PageHeader } from '../Header';
import { PageLayout } from '../Layout';
import {
  canDeleteSubItemsAtom,
  formLabelButtonsAtom,
  openFormModalAtom
} from './atoms';
import DeleteModal from './DeleteModal';
import AddModal from './Form/AddModal';
import UpdateModal from './Form/UpdateModal';
import { useGetItems } from './hooks/useGetItems';
import Listing from './Listing';
import type { CrudPageRootProps } from './models';

export const CrudPageRoot = <
  TData extends { id: number; name: string },
  TFilters,
  TItem extends { id: number; name: string },
  TItemForm
>({
  labels,
  decoder,
  queryKeyName,
  filtersAtom,
  getSearchParameters,
  baseEndpoint,
  columns,
  subItems,
  filters,
  deleteItem,
  form
}: CrudPageRootProps<TData, TFilters, TItem, TItemForm>): JSX.Element => {
  const previousCanDeleteSubItemRef = useRef<boolean | undefined>();
  const previousFormLabelButtonsRef = useRef<unknown | null>(null);
  const { isDataEmpty, hasItems, isLoading, items, total } = useGetItems<
    TData,
    TFilters
  >({
    baseEndpoint,
    decoder,
    filtersAtom,
    getSearchParameters,
    queryKeyName
  });

  const setOpenFormModal = useSetAtom(openFormModalAtom);
  const setCanDeleteSubItem = useSetAtom(canDeleteSubItemsAtom);
  const setFormLabelButton = useSetAtom(formLabelButtonsAtom);

  if (
    !equals(previousCanDeleteSubItemRef.current, subItems?.canDeleteSubItems)
  ) {
    setCanDeleteSubItem(subItems?.canDeleteSubItems ?? true);
    previousCanDeleteSubItemRef.current = subItems?.canDeleteSubItems;
  }

  if (!equals(previousFormLabelButtonsRef.current, form.labels)) {
    setFormLabelButton({
      add: {
        cancel: form.labels.add.cancel,
        confirm: form.labels.add.confirm
      },
      update: {
        cancel: form.labels.update.cancel,
        confirm: form.labels.update.confirm
      }
    });
    previousFormLabelButtonsRef.current = form.labels;
  }

  const add = useCallback(() => setOpenFormModal('add'), [setOpenFormModal]);

  if (isLoading && !hasItems) {
    return <PageSkeleton displayHeaderAndNavigation={false} />;
  }

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
        <div className="h-full w-full">
          <DataTable
            isEmpty={isDataEmpty}
            variant={isDataEmpty ? 'grid' : 'listing'}
          >
            {isDataEmpty && !isLoading ? (
              <DataTable.EmptyState
                aria-label="create"
                buttonCreateTestId="create-crudpage"
                labels={{
                  actions: labels?.actions,
                  description: labels.welcome.description,
                  title: labels.welcome.title
                }}
                onCreate={add}
              />
            ) : (
              <Listing
                columns={columns}
                filters={filters}
                isLoading={isLoading}
                labels={{
                  add: labels.actions.create,
                  search: labels.listing.search
                }}
                rows={items}
                subItems={subItems}
                total={total}
              />
            )}
          </DataTable>
          <DeleteModal<TData>
            deleteEndpoint={deleteItem.deleteEndpoint}
            labels={deleteItem.labels}
            listingQueryKey={queryKeyName}
            modalSize={deleteItem.modalSize}
          />
          <AddModal
            Form={form.Form}
            modalSize={form.modalSize}
            title={form.labels.add.title}
          />
          <UpdateModal<TItem, TItemForm>
            Form={form.Form}
            modalSize={form.modalSize}
            title={form.labels.update.title}
            {...form.getItem}
          />
        </div>
      </PageLayout.Body>
    </PageLayout>
  );
};
