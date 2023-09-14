import { useEffect, useRef } from 'react';

import { useAtomValue, useAtom, useSetAtom } from 'jotai';
import { cond, equals, isNil, or } from 'ramda';

import { Visualization } from '../models';
import { selectedVisualizationAtom } from '../Actions/actionsAtoms';
import { selectedColumnIdsAtom } from '../Listing/listingAtoms';
import { defaultSelectedColumnIds } from '../Listing/columns';

import { searchAtom } from './filterAtoms';

const useBackToVisualizationByAll = (): void => {
  const [visualization, setVisualization] = useAtom(selectedVisualizationAtom);
  const search = useAtomValue(searchAtom);
  const setSelectedColumnIds = useSetAtom(selectedColumnIdsAtom);

  const match = search.match(/^type:[^ ]+/);

  const isSearchIncludesTypeHost = match?.[0].includes('host');

  const isSearchIncludesTypesService = [
    'service',
    'metaservice',
    'anomaly-detection'
  ].some((type) => match?.[0]?.includes(type));
  const mustBackToVisualizationByAll = or(
    cond([
      [equals(Visualization.Service), () => isSearchIncludesTypeHost],
      [equals(Visualization.Host), () => isSearchIncludesTypesService]
    ])(visualization),
    isNil(match)
  );

  const initialRender = useRef(true);

  useEffect(() => {
    if (initialRender.current) {
      initialRender.current = false;

      return;
    }

    if (!mustBackToVisualizationByAll) {
      return;
    }

    setSelectedColumnIds(defaultSelectedColumnIds);
    setVisualization(Visualization.All);
  }, [search]);
};

export default useBackToVisualizationByAll;
