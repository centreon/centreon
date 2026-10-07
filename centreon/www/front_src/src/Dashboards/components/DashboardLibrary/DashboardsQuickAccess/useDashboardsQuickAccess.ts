import { useInfiniteScrollListing } from '@centreon/ui';

import { dashboardListDecoder } from '../../../api/decoders';
import { dashboardsEndpoint } from '../../../api/endpoints';
import { Dashboard, resource } from '../../../api/models';
import { quickAccessPageAtom } from './atoms';

// The quick access menu must list every dashboard, independently of the
// search, favorite filter and pagination applied on the dashboards listing.
// The next page is loaded when the user scrolls to the bottom of the menu.
const quickAccessLimit = 20;

type UseDashboardsQuickAccess = {
  dashboards: Array<Omit<Dashboard, 'refresh'>>;
  isLoading: boolean;
  loadMoreRef: (node: Element | null) => void;
};

const useDashboardsQuickAccess = (): UseDashboardsQuickAccess => {
  const { elements, elementRef, isLoading } = useInfiniteScrollListing<
    Omit<Dashboard, 'refresh'>
  >({
    decoder: dashboardListDecoder,
    endpoint: dashboardsEndpoint,
    limit: quickAccessLimit,
    pageAtom: quickAccessPageAtom,
    parameters: { apiFormat: 'Standard', sort: { name: 'asc' } },
    queryKeyName: resource.dashboards,
    suspense: false
  });

  return {
    // A refetch of an already loaded page appends its elements again: keep
    // each dashboard at its first position, with its most recent data
    dashboards: [...new Map(elements.map((d) => [d.id, d])).values()],
    isLoading,
    loadMoreRef: elementRef
  };
};

export { useDashboardsQuickAccess };
