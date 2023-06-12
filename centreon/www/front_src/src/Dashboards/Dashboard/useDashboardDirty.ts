import { useMemo } from 'react';

import { useAtomValue } from 'jotai';
import { equals, map, pick } from 'ramda';

import { Panel } from './models';
import { dashboardAtom } from './atoms';

const filterByProps = map(
  pick(['h', 'i', 'minH', 'minW', 'options', 'w', 'x', 'y'])
);

const useDashboardDirty = (initialPanels: Array<Panel>): boolean => {
  const { layout: panels } = useAtomValue(dashboardAtom);

  return useMemo(
    () => !equals(filterByProps(initialPanels), filterByProps(panels)),
    [initialPanels, panels]
  );
};

export default useDashboardDirty;
