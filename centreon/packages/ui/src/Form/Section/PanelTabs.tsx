import { prop, sortBy } from 'ramda';
import { TabI } from 'src/components/Tabs/Tabs';
import { Group } from '../Inputs/models';

const groupToTab = (groups: Array<Group>): Array<TabI> => {
  const sortedGroups = sortBy(prop('order'), groups);

  return sortedGroups.map((group) => {
    return { value: group.name, label: group.name };
  });
};

export { groupToTab };
