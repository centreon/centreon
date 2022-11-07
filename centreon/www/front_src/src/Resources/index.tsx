<<<<<<< HEAD
import { lazy } from 'react';

import { isNil } from 'ramda';
import { useAtomValue } from 'jotai/utils';

import { ListingPage, useMemoComponent, WithPanel } from '@centreon/ui';

import Details from './Details';
import EditFiltersPanel from './Filter/Edit';
import { selectedResourceIdAtom } from './Details/detailsAtoms';
import useDetails from './Details/useDetails';
import { editPanelOpenAtom } from './Filter/filterAtoms';
import useFilter from './Filter/useFilter';

const Filter = lazy(() => import('./Filter'));
const Listing = lazy(() => import('./Listing'));

const ResourcesPage = (): JSX.Element => {
  const selectedResourceId = useAtomValue(selectedResourceIdAtom);
  const editPanelOpen = useAtomValue(editPanelOpenAtom);

  return useMemoComponent({
    Component: (
      <WithPanel open={editPanelOpen} panel={<EditFiltersPanel />}>
        <ListingPage
          filter={<Filter />}
          listing={<Listing />}
          panel={<Details />}
          panelOpen={!isNil(selectedResourceId)}
        />
      </WithPanel>
    ),
    memoProps: [selectedResourceId, editPanelOpen],
  });
};

const Resources = (): JSX.Element => {
  useDetails();
  useFilter();

  return <ResourcesPage />;
=======
import * as React from 'react';

import { isNil } from 'ramda';

import { ListingPage, WithPanel } from '@centreon/ui';

import Context from './Context';
import Filter from './Filter';
import Listing from './Listing';
import Details from './Details';
import useFilter from './Filter/useFilter';
import useListing from './Listing/useListing';
import useActions from './Actions/useActions';
import useDetails from './Details/useDetails';
import EditFiltersPanel from './Filter/Edit';
import memoizeComponent from './memoizedComponent';

interface Props {
  editPanelOpen: boolean;
  selectedResourceId?: number;
}

const ResourcesPage = ({
  editPanelOpen,
  selectedResourceId,
}: Props): JSX.Element => (
  <WithPanel open={editPanelOpen} panel={<EditFiltersPanel />}>
    <ListingPage
      filter={<Filter />}
      listing={<Listing />}
      panel={<Details />}
      panelOpen={!isNil(selectedResourceId)}
    />
  </WithPanel>
);

const memoProps = ['editPanelOpen', 'selectedResourceId'];

const MemoizedResourcesPage = memoizeComponent<Props>({
  Component: ResourcesPage,
  memoProps,
});

const Resources = (): JSX.Element => {
  const listingContext = useListing();
  const filterContext = useFilter();
  const detailsContext = useDetails();
  const actionsContext = useActions();

  const { selectedResourceId } = detailsContext;

  return (
    <Context.Provider
      value={{
        ...listingContext,
        ...filterContext,
        ...detailsContext,
        ...actionsContext,
      }}
    >
      <MemoizedResourcesPage
        editPanelOpen={filterContext.editPanelOpen}
        selectedResourceId={selectedResourceId}
      />
    </Context.Provider>
  );
>>>>>>> centreon/dev-21.10.x
};

export default Resources;
