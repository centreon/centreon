import type { Column } from '@centreon/ui';
import {
  ColumnType,
  truncate,
  useLocaleDateTimeFormat,
  useStyleTable
} from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import {
  always,
  cond,
  equals,
  head,
  isEmpty,
  isNotNil,
  or,
  pipe,
  propOr,
  split,
  T
} from 'ramda';
import { useTranslation } from 'react-i18next';

import { openTicketAtom } from '../../atom';
import { DisplayType } from '../models';
import {
  labelAction,
  labelAlias,
  labelDuration,
  labelFqdn,
  labelHost,
  labelInformation,
  labelLastCheck,
  labelMonitoringServer,
  labelOpenedOn,
  labelParent,
  labelParentAlias,
  labelResource,
  labelService,
  labelServices,
  labelSeverity,
  labelState,
  labelStatus,
  labelTicket,
  labelTicketID,
  labelTicketSubject,
  labelTries
} from '../translatedLabels';
import CloseTicket from './CloseTicket/CloseTicket';
import { useStatusStyles } from './Columns.styles';
import OpenTicket from './OpenTicket/OpenTicket';
import { TicketLink } from './OpenTicket/TicketLink';
import ParentResourceColumn from './Parent';
import ResourceColumn from './Resource';
import SubItem from './ServiceSubItemColumn/SubItem';
import SeverityColumn from './Severity';
import StateColumn from './State';
import StatusColumn from './Status';

interface ColumnProps {
  displayType?: DisplayType;
}

interface ColumnsState {
  columns: Array<Column>;
  defaultSelectedColumnIds: Array<string>;
}

const getTicketInformations = (row) =>
  row?.extra?.open_tickets?.tickets ||
  row?.parent?.extra?.open_tickets?.tickets;

const useColumns = ({
  displayType = DisplayType.All
}: ColumnProps): ColumnsState => {
  const { dataStyle } = useStyleTable({});
  const { classes: statusClasses } = useStatusStyles({
    data: dataStyle.statusColumnChip
  });

  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);
  const {
    displayResources,
    enableHostTicketCreation,
    enableServiceTicketCreation,
    isOpenTicketEnabled,
    isOpenTicketInstalled,
    provider
  } = useAtomValue(openTicketAtom);

  const { format } = useLocaleDateTimeFormat();
  const { t } = useTranslation();

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
    isOpenTicketInstalled &&
    isOpenTicketEnabled &&
    hasProvider &&
    or(enableHostTicketCreation, enableServiceTicketCreation);

  const isOpenTicketActionColumnVisible =
    isOpenTicketColumnsVisible && equals(displayResources, 'withoutTicket');

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
        isOnPublicPage,
        t
      }),
      clickable: true,
      getRenderComponentOnRowUpdateCondition: T,
      hasHoverableComponent: true,
      id: 'status',
      label: t(labelStatus),
      rowMemoProps: ['status', 'severity_code', 'type'],
      sortable: true,
      sortField: 'status_severity_code',
      type: ColumnType.component,
      width: 'max-content'
    },
    {
      Component: ResourceColumn({ displayType }),
      getRenderComponentOnRowUpdateCondition: T,
      id: 'resource',
      label: t(resourceLabel),
      rowMemoProps: ['icon', 'short_type', 'name'],
      sortable: true,
      sortField: 'name',
      type: ColumnType.component,
      width: 'max-content'
    },
    {
      Component: equals(displayType, DisplayType.Host)
        ? SubItem
        : ParentResourceColumn,
      displaySubItemsCaret: !!equals(displayType, DisplayType.Host),
      getRenderComponentOnRowUpdateCondition: T,
      id: 'parent_resource',
      label: t(parentLabel),
      sortable: true,
      sortField: 'parent_name',
      type: ColumnType.component,
      width: 'max-content'
    },
    ...(isOpenTicketActionColumnVisible && !isOnPublicPage
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
            Component: TicketLink,
            clickable: true,
            id: 'ticket_id',
            label: t(labelTicketID),
            type: ColumnType.component
          },
          {
            getFormattedString: (row): string =>
              getTicketInformations(row)?.subject,
            id: 'ticket_subject',
            label: t(labelTicketSubject),
            type: ColumnType.string
          },
          {
            getFormattedString: (row): string =>
              getTicketInformations(row)?.created_at
                ? format({
                    date: getTicketInformations(row)?.created_at,
                    formatString: 'L'
                  })
                : '',
            id: 'ticket_open_time',
            label: t(labelOpenedOn),
            type: ColumnType.string
          }
        ]
      : []),
    {
      getFormattedString: ({ duration }): string => duration,
      id: 'duration',
      label: t(labelDuration),
      sortable: true,
      sortField: 'last_status_change',
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
        (information: string) => truncate({ content: information })
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
      sortable: true,
      sortField: 'severity_level',
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
    {
      getFormattedString: ({ alias }): string => alias,
      id: 'alias',
      label: t(labelAlias),
      sortable: true,
      type: ColumnType.string,
      width: 'max-content'
    },
    {
      getFormattedString: ({ parent }): string => parent?.alias,
      id: 'parent_alias',
      label: t(labelParentAlias),
      rowMemoProps: ['parent'],
      sortable: true,
      sortField: 'parent_alias',
      type: ColumnType.string,
      width: 'max-content'
    },
    {
      getFormattedString: ({ fqdn }): string => fqdn,
      id: 'fqdn',
      label: t(labelFqdn),
      sortable: true,
      type: ColumnType.string,
      width: 'max-content'
    },
    {
      getFormattedString: ({ monitoring_server_name }): string =>
        monitoring_server_name,
      id: 'monitoring_server_name',
      label: t(labelMonitoringServer),
      sortable: true,
      type: ColumnType.string,
      width: 'max-content'
    },
    ...(areTicketColumnsVisible && !isOnPublicPage
      ? [
          {
            Component: CloseTicket,
            clickable: true,
            getRenderComponentOnRowUpdateCondition: T,
            id: 'action',
            label: t(labelAction),
            type: ColumnType.component
          }
        ]
      : [])
  ];

  return { columns, defaultSelectedColumnIds };
};
export default useColumns;
