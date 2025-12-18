import type { Column } from '@centreon/ui';
import { ColumnType, truncate } from '@centreon/ui';
import { FeatureFlags } from '@centreon/ui-context';

import { equals, head, pipe, propOr, split, T } from 'ramda';

import { Visualization } from '../../models';
import {
  labelAction,
  labelAlias,
  labelCheck,
  labelDuration,
  labelFqdn,
  labelGraph,
  labelHost,
  labelInformation,
  labelLastCheck,
  labelMonitoringServer,
  labelNotes,
  labelNotification,
  labelParent,
  labelParentAlias,
  labelResource,
  labelService,
  labelServices,
  labelSeverity,
  labelState,
  labelStatus,
  labelTries
} from '../../translatedLabels';
import ChecksColumn from './Checks';
import GraphColumn from './Graph';
import NotificationColumn from './Notification';
import ParentResourceColumn from './Parent';
import ResourceColumn from './Resource';
import SubItem from './ServiceSubItemColumn/SubItem';
import SeverityColumn from './Severity';
import StateColumn from './State';
import StatusColumn from './Status';
import ActionUrlColumn from './Url/Action';
import NotesUrlColumn from './Url/Notes';

export interface ColumnProps {
  actions;
  featureFlags?: FeatureFlags | null;
  t: (value: string) => string;
  visualization?: Visualization;
}

export const defaultSelectedColumnIds = [
  'status',
  'resource',
  'parent_resource',
  'graph',
  'duration',
  'last_check',
  'information',
  'tries'
];

export const defaultSelectedColumnIdsforViewByHost = [
  'resource',
  'services',
  'state',
  'graph',
  'duration',
  'last_check',
  'information',
  'tries'
];

export const getColumns = ({
  actions,
  visualization = Visualization.All,
  featureFlags,
  t
}: ColumnProps): Array<Column> => {
  const isViewByService = equals(visualization, Visualization.Service);
  const isViewByHost = equals(visualization, Visualization.Host);

  const resourceLabel = isViewByHost
    ? labelHost
    : isViewByService
      ? labelService
      : labelResource;

  const parentLabel = isViewByService ? labelHost : labelParent;

  const columns = [
    ...(isViewByHost
      ? []
      : [
          {
            Component: StatusColumn({ actions, t }),
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
          }
        ]),
    {
      Component: ResourceColumn,
      getRenderComponentOnRowUpdateCondition: T,
      id: 'resource',
      label: t(resourceLabel),
      rowMemoProps: ['icon', 'short_type', 'name'],
      sortable: true,
      sortField: 'name',
      type: ColumnType.component,
      width: 'max-content'
    },
    ...(isViewByHost
      ? [
          {
            Component: SubItem,
            displaySubItemsCaret: true,
            id: 'services',
            label: t(labelServices),
            type: ColumnType.component,
            width: 'max-content'
          }
        ]
      : [
          {
            Component: ParentResourceColumn,
            getRenderComponentOnRowUpdateCondition: T,
            id: 'parent_resource',
            label: t(parentLabel),
            rowMemoProps: ['parent'],
            sortable: true,
            sortField: 'parent_name',
            type: ColumnType.component,
            width: 'max-content'
          }
        ]),
    {
      Component: GraphColumn({ onClick: actions.onDisplayGraph }),
      getRenderComponentOnRowUpdateCondition: T,
      id: 'graph',
      label: t(labelGraph),
      shortLabel: 'G',
      sortable: false,
      type: ColumnType.component
    },
    {
      getFormattedString: ({ duration }): string => duration,
      id: 'duration',
      label: t(labelDuration),
      sortable: true,
      sortField: 'last_status_change',
      type: ColumnType.string,
      width: 'max-content'
    },
    {
      getFormattedString: ({ tries }): string => tries,
      id: 'tries',
      label: t(labelTries),
      sortable: true,
      type: ColumnType.string,
      width: 'max-content'
    },
    {
      getFormattedString: ({ last_check }): string => last_check,
      id: 'last_check',
      label: t(labelLastCheck),
      sortable: true,
      type: ColumnType.string,
      width: 'max-content'
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
      shortLabel: 'S',
      sortable: true,
      sortField: 'severity_level',
      type: ColumnType.component
    },
    {
      Component: NotesUrlColumn,
      getRenderComponentOnRowUpdateCondition: T,
      id: 'notes_url',
      label: t(labelNotes),
      rowMemoProps: ['links'],
      shortLabel: 'N',
      sortable: false,
      type: ColumnType.component
    },
    {
      Component: ActionUrlColumn,
      getRenderComponentOnRowUpdateCondition: T,
      id: 'action_url',
      label: t(labelAction),
      rowMemoProps: ['links'],
      shortLabel: 'A',
      sortable: false,
      type: ColumnType.component
    },
    {
      Component: StateColumn,
      getRenderComponentOnRowUpdateCondition: T,
      id: 'state',
      label: t(labelState),
      rowMemoProps: ['is_in_downtime', 'is_acknowledged', 'name', 'links'],
      sortable: false,
      type: ColumnType.component,
      width: 'max-content'
    },
    {
      getFormattedString: ({ alias }): string => alias,
      id: 'alias',
      label: t(labelAlias),
      sortable: true,
      type: ColumnType.string,
      width: 'max-content'
    },
    ...(isViewByHost
      ? []
      : [
          {
            getFormattedString: ({ parent }): string => parent?.alias,
            id: 'parent_alias',
            label: t(labelParentAlias),
            rowMemoProps: ['parent'],
            sortable: true,
            sortField: 'parent_alias',
            type: ColumnType.string,
            width: 'max-content'
          }
        ]),
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
    ...(featureFlags?.notification
      ? []
      : [
          {
            Component: NotificationColumn,
            getRenderComponentOnRowUpdateCondition: T,
            id: 'notification',
            label: t(labelNotification),
            rowMemoProps: ['is_notification_enabled'],
            shortLabel: 'Notif',
            type: ColumnType.component
          }
        ]),
    {
      Component: ChecksColumn,
      getRenderComponentOnRowUpdateCondition: T,
      id: 'checks',
      label: t(labelCheck),
      rowMemoProps: ['has_passive_checks_enabled', 'has_active_checks_enabled'],
      shortLabel: 'C',
      type: ColumnType.component
    }
  ];

  return columns;
};
