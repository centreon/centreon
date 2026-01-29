import { prop, sortBy } from 'ramda';
import type { TabI } from 'src/components/Tabs/Tabs';

import type { Group } from '../Inputs/models';

const groupToTab = (groups?: Array<Group>): Array<TabI> => {
  if (!groups) {
    return [];
  }
  const sortedGroups = sortBy(prop('order'), groups);

  return sortedGroups.map((group) => {
    return { label: group.name, value: group.name };
  });
};

export { groupToTab };
