import { useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useCallback, useRef } from 'react';
import PageSkeleton from '../../PageSkeleton';
import { DataTable } from '../DataTable';
import { PageHeader } from '../Header';
import { PageLayout } from '../Layout';
import DeleteModal from './DeleteModal';
import AddModal from './Form/AddModal';
import UpdateModal from './Form/UpdateModal';
import Listing from './Listing';
import {
  canDeleteSubItemsAtom,
  formLabelButtonsAtom,
  openFormModalAtom
} from './atoms';
import { useGetItems } from './hooks/useGetItems';
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
    queryKeyName,
    filtersAtom,
    decoder,
    getSearchParameters,
    baseEndpoint
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

  const add = useCallback(() => setOpenFormModal('add'), []);

  if (isLoading && !hasItems) {
    return <PageSkeleton displayHeaderAndNavigation={false} />;
  }

  return (
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Title title={labels.title} />
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <>
          <DataTable
            isEmpty={isDataEmpty}
            variant={isDataEmpty ? 'grid' : 'listing'}
          >
            {isDataEmpty && !isLoading ? (
              <DataTable.EmptyState
                aria-label="create"
                data-testid="create-agent-configuration"
                labels={{
                  title: labels.welcome.title,
                  description: labels.welcome.description,
                  actions: labels?.actions
                }}
                onCreate={add}
              />
            ) : (
              <Listing
                total={total}
                isLoading={isLoading}
                rows={items}
                columns={columns}
                subItems={subItems}
                labels={{
                  add: labels.actions.create,
                  search: labels.listing.search
                }}
                filters={filters}
              />
            )}
          </DataTable>
          <DeleteModal<TData>
            listingQueryKey={queryKeyName}
            deleteEndpoint={deleteItem.deleteEndpoint}
            labels={deleteItem.labels}
            modalSize={deleteItem.modalSize}
          />
          <AddModal
            title={form.labels.add.title}
            Form={form.Form}
            modalSize={form.modalSize}
          />
          <UpdateModal<TItem, TItemForm>
            title={form.labels.update.title}
            Form={form.Form}
            modalSize={form.modalSize}
            {...form.getItem}
          />
        </>
      </PageLayout.Body>
    </PageLayout>
  );
};
