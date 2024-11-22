import { useMemo } from 'react';

import { useAtomValue } from 'jotai';
import { equals, map, pick } from 'ramda';

import { dashboardAtom } from '../atoms';
import { Panel } from '../models';

const filterByProps = map(
  pick(['h', 'i', 'minH', 'minW', 'options', 'w', 'x', 'y', 'data'])
);

const useDashboardDirty = (initialPanels: Array<Panel>): boolean => {
  const { layout: panels } = useAtomValue(dashboardAtom);

  return useMemo(
    () => !equals(filterByProps(initialPanels), filterByProps(panels)),
    [initialPanels, panels]
  );
};

export default useDashboardDirty;
