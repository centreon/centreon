import { Suspense, lazy, useEffect } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { isNil } from 'ramda';

import {
  ListingPage,
  LoadingSkeleton,
  WithPanel,
  useMemoComponent
} from '@centreon/ui';

import Details from './Details';
import {
  clearSelectedResourceDerivedAtom,
  selectedResourcesDetailsAtom
} from './Details/detailsAtoms';
import useDetails from './Details/useDetails';
import { editPanelOpenAtom } from './Filter/filterAtoms';
import useFilter from './Filter/useFilter';

const EditFiltersPanel = lazy(() => import('./Filter/Edit'));

const Filter = lazy(() => import('./Filter'));
const Listing = lazy(() => import('./Listing'));

const ResourcesPage = (): JSX.Element => {
  const selectedResource = useAtomValue(selectedResourcesDetailsAtom);
  const editPanelOpen = useAtomValue(editPanelOpenAtom);
  const clearSelectedResource = useSetAtom(clearSelectedResourceDerivedAtom);

  useEffect(() => {
    window.addEventListener('beforeunload', clearSelectedResource);

    return () => {
      window.removeEventListener('beforeunload', clearSelectedResource);
      clearSelectedResource();
    };
  }, []);

  return useMemoComponent({
    Component: (
      <WithPanel
        open={editPanelOpen}
        panel={
          editPanelOpen ? (
            <Suspense fallback={<LoadingSkeleton height="100%" width={550} />}>
              <EditFiltersPanel />
            </Suspense>
          ) : undefined
        }
      >
        <ListingPage
          filter={<Filter />}
          listing={<Listing />}
          panel={<Details />}
          panelOpen={!isNil(selectedResource?.resourceId)}
        />
      </WithPanel>
    ),
    memoProps: [selectedResource?.resourceId, editPanelOpen]
  });
};

const Resources = (): JSX.Element => {
  useDetails();
  useFilter();

  return <ResourcesPage />;
};

export default Resources;
