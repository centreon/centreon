import { useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useCallback, useRef } from 'react';
import PageSkeleton from '../../PageSkeleton';
import { DataTable } from '../DataTable';
import { PageHeader } from '../Header';
import { PageLayout } from '../Layout';
import DeleteModal from './DeletEModal';
import Listing from './Listing';
import { isDeleteEnabledAtom, openFormModalAtom } from './atoms';
import { useGetItems } from './hooks/useGetItems';
import type { CrudPageRootProps } from './models';

export const CrudPageRoot = <
  TData extends { id: number; name: string },
  TFilters
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
  deleteItem
}: CrudPageRootProps<TData, TFilters>): JSX.Element => {
  const previousIsDeleteEnabledRef = useRef(false);
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
  const setIsDeleteEnabled = useSetAtom(isDeleteEnabledAtom);

  if (!equals(previousIsDeleteEnabledRef.current, deleteItem.enabled)) {
    setIsDeleteEnabled(deleteItem.enabled);
    previousIsDeleteEnabledRef.current = deleteItem.enabled;
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
          {deleteItem.enabled && (
            <DeleteModal<TData>
              listingQueryKey={queryKeyName}
              deleteEndpoint={deleteItem.deleteEndpoint}
              labels={deleteItem.labels}
            />
          )}
        </>
      </PageLayout.Body>
    </PageLayout>
  );
};
