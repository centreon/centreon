import { pipe, T, equals, head, split, propOr, cond, always } from 'ramda';
import { useTranslation } from 'react-i18next';

import { ColumnType, useStyleTable } from '@centreon/ui';
import type { Column } from '@centreon/ui';

import {
  labelResource,
  labelStatus,
  labelDuration,
  labelTries,
  labelState,
  labelLastCheck,
  labelParent,
  labelSeverity,
  labelService,
  labelHost,
  labelServices,
  labelInformation
} from '../translatedLabels';
import { DisplayType } from '../models';

import StateColumn from './State';
import StatusColumn from './Status';
import SeverityColumn from './Severity';
import ResourceColumn from './Resource';
import ParentResourceColumn from './Parent';
import SubItem from './ServiceSubItemColumn/SubItem';
import useStyles, { useStatusStyles } from './Columns.styles';
import truncate from './truncate';

interface ColumnProps {
  displayType?: DisplayType;
}

const useColumns = ({
  displayType = DisplayType.All
}: ColumnProps): Array<Column> => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const { dataStyle } = useStyleTable({});
  const { classes: statusClasses } = useStatusStyles({
    data: dataStyle.statusColumnChip
  });

  const resourceLabel = cond([
    [equals(DisplayType.Host), always(labelHost)],
    [equals(DisplayType.Service), always(labelService)],
    [T, always(labelResource)]
  ])(displayType);

  const parentLabel = cond([
    [equals(DisplayType.Host), always(labelServices)],
    [equals(DisplayType.Service), always(labelHost)],
    [T, always(labelParent)]
  ])(displayType);

  const columns = [
    {
      Component: StatusColumn({ classes: statusClasses, displayType, t }),
      clickable: true,
      getRenderComponentOnRowUpdateCondition: T,
      hasHoverableComponent: true,
      id: 'status',
      label: t(labelStatus),
      rowMemoProps: ['status', 'severity_code', 'type'],
      sortField: 'status_severity_code',
      sortable: true,
      type: ColumnType.component,
      width: 'max-content'
    },
    {
      Component: ResourceColumn({ classes, displayType }),
      getRenderComponentOnRowUpdateCondition: T,
      id: 'resource',
      label: t(resourceLabel),
      rowMemoProps: ['icon', 'short_type', 'name'],
      sortField: 'name',
      sortable: true,
      type: ColumnType.component
    },
    {
      Component: equals(displayType, DisplayType.Host)
        ? SubItem
        : ParentResourceColumn,
      displaySubItemsCaret: !!equals(displayType, DisplayType.Host),
      getRenderComponentOnRowUpdateCondition: T,
      id: 'parent_resource',
      label: t(parentLabel),
      sortField: 'parent_name',
      sortable: true,
      type: ColumnType.component
    },
    {
      getFormattedString: ({ duration }): string => duration,
      id: 'duration',
      label: t(labelDuration),
      sortField: 'last_status_change',
      sortable: true,
      type: ColumnType.string
    },
    {
      getFormattedString: ({ tries }): string => tries,
      id: 'tries',
      label: t(labelTries),
      sortable: true,
      type: ColumnType.string
    },
    {
      getFormattedString: ({ last_check }): string => last_check,
      id: 'last_check',
      label: t(labelLastCheck),
      sortable: true,
      type: ColumnType.string
    },
    {
      getFormattedString: pipe(
        propOr('', 'information'),
        split('\n'),
        head,
        truncate
      ) as (row) => string,
      id: 'information',
      label: t(labelInformation),
      rowMemoProps: ['information'],
      sortable: false,
      type: ColumnType.string,
      width: 'minmax(100px, 1fr)'
    },
    {
      Component: SeverityColumn,
      getRenderComponentOnRowUpdateCondition: T,
      id: 'severity',
      label: t(labelSeverity),
      rowMemoProps: ['severity_level'],
      sortField: 'severity_level',
      sortable: true,
      type: ColumnType.component,
      width: 'minmax(50px, auto)'
    },
    {
      Component: StateColumn,
      getRenderComponentOnRowUpdateCondition: T,
      id: 'state',
      label: t(labelState),
      rowMemoProps: ['is_in_downtime', 'is_acknowledged', 'name', 'links'],
      sortable: false,
      type: ColumnType.component
    }
  ];

  return columns;
};
export default useColumns;
