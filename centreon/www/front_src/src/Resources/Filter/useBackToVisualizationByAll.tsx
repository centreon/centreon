import { useEffect } from 'react';

import { useAtomValue, useAtom } from 'jotai';
import { cond, equals, isNil, or } from 'ramda';

import { Visualization } from '../models';
import { selectedVisualizationAtom } from '../Actions/actionsAtoms';

import { searchAtom } from './filterAtoms';

const useBackToVisualizationByAll = (): void => {
  const [visualization, setVisualization] = useAtom(selectedVisualizationAtom);
  const search = useAtomValue(searchAtom);

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

  useEffect(() => {
    if (!mustBackToVisualizationByAll) {
      return;
    }

    setVisualization(Visualization.All);
  }, [search]);
};

export default useBackToVisualizationByAll;
