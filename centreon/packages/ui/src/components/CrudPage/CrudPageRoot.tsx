import { useSetAtom } from 'jotai';
import { useCallback } from 'react';
import PageSkeleton from '../../PageSkeleton';
import { DataTable } from '../DataTable';
import { PageHeader } from '../Header';
import { PageLayout } from '../Layout';
import Listing from './Listing';
import { openFormModalAtom } from './atoms';
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
  subItems
}: CrudPageRootProps<TData, TFilters>): JSX.Element => {
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
            />
          )}
        </DataTable>
      </PageLayout.Body>
    </PageLayout>
  );
};
