import { useEffect, useRef } from 'react';

import { useAtomValue, useAtom, useSetAtom } from 'jotai';
import { and, equals, isNil, or } from 'ramda';

import { Visualization } from '../models';
import { selectedVisualizationAtom } from '../Actions/actionsAtoms';
import { selectedColumnIdsAtom } from '../Listing/listingAtoms';
import { defaultSelectedColumnIds } from '../Listing/columns';

import { applyCurrentFilterDerivedAtom, searchAtom } from './filterAtoms';

const useBackToVisualizationByAll = (): void => {
  const [visualization, setVisualization] = useAtom(selectedVisualizationAtom);
  const search = useAtomValue(searchAtom);
  const applyCurrentFilter = useSetAtom(applyCurrentFilterDerivedAtom);

  const setSelectedColumnIds = useSetAtom(selectedColumnIdsAtom);
  const initialRender = useRef(true);

  const isViewByHost = equals(visualization, Visualization.Host);
  const isViewByService = equals(visualization, Visualization.Service);
  const isViewByAll = or(isViewByHost, isViewByService);

  const searchType = search.match(/type:[^ ]+/);

  const isSearchIncludesTypeHost =
    isViewByService && searchType?.[0].includes('host');

  const isSearchIncludesTypesService =
    isViewByHost &&
    ['service', 'metaservice', 'anomaly-detection'].some((type) =>
      searchType?.[0].includes(type)
    );

  const isSearchIncludesOtherTypes = or(
    isSearchIncludesTypeHost,
    isSearchIncludesTypesService
  );

  const mustBackToVisualizationByAll = and(
    isViewByAll,
    or(isSearchIncludesOtherTypes, isNil(searchType))
  );

  const selectVisualizationByAll = (): void => {
    applyCurrentFilter();
    setSelectedColumnIds(defaultSelectedColumnIds);
    setVisualization(Visualization.All);
  };

  useEffect(() => {
    if (initialRender.current) {
      initialRender.current = false;

      return;
    }

    if (!mustBackToVisualizationByAll) {
      return;
    }

    selectVisualizationByAll();
  }, [search]);
};

export default useBackToVisualizationByAll;
