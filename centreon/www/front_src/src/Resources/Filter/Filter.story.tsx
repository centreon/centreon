import { TestQueryProvider } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';

import { retrievedUser } from '../../Main/testUtils';
import { selectedVisualizationAtom } from '../Actions/actionsAtoms';
import { enabledAutorefreshAtom } from '../Listing/listingAtoms';
import { Visualization } from '../models';
import {
  labelHost,
  labelMetaService,
  labelNewFilter,
  labelService
} from '../translatedLabels';
import Filter from '.';
import getDefaultCriterias from './Criterias/default';
import { applyFilterDerivedAtom } from './filterAtoms';
import { allFilter, unhandledProblemsFilter } from './models';

// The resource types a view by host or by service applies.
const viewResourceTypes = {
  [Visualization.Host]: [{ id: 'host', name: labelHost }],
  [Visualization.Service]: [
    { id: 'service', name: labelService },
    { id: 'metaservice', name: labelMetaService }
  ]
};

const getViewFilter = (view: Visualization): object => {
  if (view === Visualization.All) {
    return allFilter;
  }

  return {
    criterias: [
      ...getDefaultCriterias(),
      {
        name: 'resource_types',
        object_type: null,
        type: 'multi_select',
        value: viewResourceTypes[view]
      }
    ],
    id: '',
    name: labelNewFilter
  };
};

interface Props {
  unhandledProblems?: boolean;
  view?: Visualization;
}

export const Default = ({
  unhandledProblems = false,
  view = Visualization.All
}: Props): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    created.set(userAtom, retrievedUser as never);
    created.set(selectedVisualizationAtom, view);
    created.set(enabledAutorefreshAtom, false);
    created.set(
      applyFilterDerivedAtom,
      (unhandledProblems
        ? unhandledProblemsFilter
        : getViewFilter(view)) as never
    );

    return created;
  }, [unhandledProblems, view]);

  return (
    <TestQueryProvider>
      <Provider store={store}>
        <Filter />
      </Provider>
    </TestQueryProvider>
  );
};
