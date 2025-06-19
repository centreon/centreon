import { prop, sortBy } from 'ramda';
import { Tab } from 'src/components/Tabs/Tabs';
import { Group } from '../Inputs/models';

const groupToTab = (groups: Group[]): Tab[] => {
  const sortedGroups = sortBy(prop('order'), groups);

  return sortedGroups.map((group) => {
    return { value: group.name, label: group.name };
  });
};

export { groupToTab };
