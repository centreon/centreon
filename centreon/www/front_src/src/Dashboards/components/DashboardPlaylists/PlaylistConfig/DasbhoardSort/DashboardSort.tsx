import { useTranslation } from 'react-i18next';
import { verticalListSortingStrategy } from '@dnd-kit/sortable';
import { closestCenter } from '@dnd-kit/core';
import { isEmpty } from 'ramda';

import { SortableItems } from '@centreon/ui';

import Subtitle from '../../../../Dashboard/components/Subtitle';

import { useDashboardSort } from './useDashboardSort';
import Content from './Content';
import { useDashboardSortStyles } from './DashboardSort.styles';

import { labelDefineTheOrderOfDashboards } from 'www/front_src/src/Dashboards/translatedLabels';

const DashboardSort = (): JSX.Element | null => {
  const { t } = useTranslation();
  const { classes } = useDashboardSortStyles();

  const { sortedDashboards, sortDashboards } = useDashboardSort();

  if (isEmpty(sortedDashboards)) {
    return null;
  }

  return (
    <div>
      <Subtitle>{t(labelDefineTheOrderOfDashboards)}</Subtitle>
      <div className={classes.items}>
        <SortableItems
          updateSortableItemsOnItemsChange
          Content={Content}
          collisionDetection={closestCenter}
          itemProps={['id', 'order', 'name']}
          items={sortedDashboards}
          sortingStrategy={verticalListSortingStrategy}
          onDragEnd={({ items }): void => {
            sortDashboards(items);
          }}
        />
      </div>
    </div>
  );
};

export default DashboardSort;
