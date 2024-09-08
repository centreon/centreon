import {
  T,
  always,
  cond,
  equals,
  head,
  isEmpty,
  isNotNil,
  pipe,
  propOr,
  split
} from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  ColumnType,
  useLocaleDateTimeFormat,
  useStyleTable
} from '@centreon/ui';
import type { Column } from '@centreon/ui';

import { DisplayType } from '../models';
import {
  labelAction,
  labelDuration,
  labelHost,
  labelInformation,
  labelLastCheck,
  labelParent,
  labelResource,
  labelService,
  labelServices,
  labelSeverity,
  labelState,
  labelStatus,
  labelTicket,
  labelTicketID,
  labelTicketOpenTime,
  labelTicketSubject,
  labelTries
} from '../translatedLabels';

import useIsOpenTicketInstalled from '../useIsOpenTicketInstalled';
import CloseTicket from './CloseTicket/CloseTicket';
import useStyles, { useStatusStyles } from './Columns.styles';
import OpenTicket from './OpenTicket/OpenTicket';
import ParentResourceColumn from './Parent';
import ResourceColumn from './Resource';
import SubItem from './ServiceSubItemColumn/SubItem';
import SeverityColumn from './Severity';
import StateColumn from './State';
import StatusColumn from './Status';
import truncate from './truncate';

interface ColumnProps {
  displayResources: 'all' | 'withTicket' | 'withoutTicket';
  displayType?: DisplayType;
  isOpenTicketEnabled: boolean;
  provider?: { id: number; name: string };
}

interface ColumnsState {
  columns: Array<Column>;
  defaultSelectedColumnIds: Array<string>;
}

const useColumns = ({
  displayType = DisplayType.All,
  displayResources = 'all',
  provider,
  isOpenTicketEnabled
}: ColumnProps): ColumnsState => {
  const { classes } = useStyles();
  const { dataStyle } = useStyleTable({});
  const { classes: statusClasses } = useStatusStyles({
    data: dataStyle.statusColumnChip
  });

  const { format } = useLocaleDateTimeFormat();
  const { t } = useTranslation();

  const isOpenTicketInstalled = useIsOpenTicketInstalled();

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

  const hasProvider = isNotNil(provider) && !isEmpty(provider);
  const isOpenTicketColumnsVisible =
    isOpenTicketInstalled && isOpenTicketEnabled && hasProvider;

  const isOpenTicketActionColumnVisible =
    isOpenTicketColumnsVisible && !equals(displayResources, 'withTicket');

  const areTicketColumnsVisible =
    isOpenTicketColumnsVisible && equals(displayResources, 'withTicket');

  const defaultSelectedColumnIds = [
    'status',
    'resource',
    'parent_resource',
    ...(isOpenTicketActionColumnVisible ? ['open_ticket'] : []),
    ...(areTicketColumnsVisible
      ? ['ticket_id', 'ticket_subject', 'ticket_open_time', 'action']
      : ['state', 'severity', 'duration', 'last_check'])
  ];

  const columns = [
    {
      Component: StatusColumn({
        classes: statusClasses,
        displayType,
        t
      }),
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
    ...(isOpenTicketActionColumnVisible
      ? [
          {
            Component: OpenTicket,
            clickable: true,
            id: 'open_ticket',
            label: t(labelTicket),
            type: ColumnType.component
          }
        ]
      : []),
    ...(areTicketColumnsVisible
      ? [
          {
            getFormattedString: (row): string =>
              row?.extra?.open_tickets?.tickets.id,
            id: 'ticket_id',
            label: t(labelTicketID),
            type: ColumnType.string
          }
        ]
      : []),

    ...(areTicketColumnsVisible
      ? [
          {
            getFormattedString: (row): string =>
              row?.extra?.open_tickets?.tickets?.subject,
            id: 'ticket_subject',
            label: t(labelTicketSubject),
            type: ColumnType.string
          }
        ]
      : []),
    ...(areTicketColumnsVisible
      ? [
          {
            getFormattedString: (row): string =>
              row?.extra?.open_tickets?.tickets?.created_at
                ? format({
                    date: row?.extra?.open_tickets?.tickets?.created_at,
                    formatString: 'L'
                  })
                : '',
            id: 'ticket_open_time',
            label: t(labelTicketOpenTime),
            type: ColumnType.string
          }
        ]
      : []),
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
    },
    ...(areTicketColumnsVisible
      ? [
          {
            Component: CloseTicket,
            getRenderComponentOnRowUpdateCondition: T,
            id: 'action',
            label: t(labelAction),
            type: ColumnType.component,
            clickable: true
          }
        ]
      : [])
  ];

  return { columns, defaultSelectedColumnIds };
};
export default useColumns;
