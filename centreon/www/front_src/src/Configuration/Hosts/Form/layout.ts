import type { TFunction } from 'i18next';

import {
  schedulingColumns,
  stackedColumns,
  threeColumnsFromWide,
  twoColumns,
  twoColumnsFromNarrow,
  twoColumnsFromWide
} from '../../ResourceForm/grid';
import {
  getPlacedBlockIds as getLayoutBlockIds,
  type LayoutNode,
  type LayoutSection,
  type PlacedSection,
  placeBlocks as placeLayoutBlocks
} from '../../ResourceForm/layout';
import {
  labelAdvanced,
  labelGeneralInformation,
  labelHostDetails,
  labelHostGroups,
  labelHostMonitoringProperties,
  labelMonitoringEngineSchedulingOptions,
  labelNotifications,
  labelParentChildRelations,
  labelRelations,
  labelSchedulingOptions
} from '../translatedLabels';
import { type BlockId, type Blocks, hostResource } from './sections';

// The one place that says where each block of the data sections is shown, as
// in the design. Every block is placed exactly once (`layout.test.ts`).
export const layout: Array<LayoutSection> = [
  { label: labelGeneralInformation, nodes: ['basicInformation'] },
  {
    label: labelHostMonitoringProperties,
    nodes: [
      {
        // The check command and its arguments on the left, the SNMP community
        // and its version on the right, with room above the templates.
        className: `${twoColumns} pb-4`,
        columnSeparator: true,
        columns: ['checkCommand', 'snmp'],
        name: 'check-snmp-layout'
      },
      {
        className: twoColumnsFromWide,
        columnSeparator: true,
        columns: ['templates', 'macros'],
        name: 'templates-layout'
      }
    ]
  },
  {
    label: labelRelations,
    nodes: [
      {
        className: twoColumnsFromNarrow,
        columnSeparator: true,
        columns: [
          {
            className: stackedColumns,
            columns: [
              'groups',
              {
                className: stackedColumns,
                columns: ['parentHosts', 'childHosts'],
                name: 'parent-child-relations',
                title: labelParentChildRelations
              }
            ],
            name: 'relations-left'
          },
          {
            className: stackedColumns,
            columns: ['categories', 'severity'],
            name: 'relations-right'
          }
        ],
        name: 'relations-layout',
        // Over both columns: the host categories stay level with the host
        // groups, as designed.
        title: labelHostGroups
      }
    ]
  },
  {
    label: labelMonitoringEngineSchedulingOptions,
    nodes: [
      {
        className: twoColumnsFromNarrow,
        columnSeparator: true,
        columns: [
          {
            className: twoColumnsFromWide,
            columns: [
              'notesAndIcons',
              {
                className: stackedColumns,
                columns: ['actionUrlAndGeoCoordinates', 'timezone', 'poller'],
                name: 'host-details-right'
              }
            ],
            name: 'host-details',
            title: labelHostDetails
          },
          {
            className: schedulingColumns,
            columnSeparator: true,
            columns: ['checkPeriodAndIntervals', 'activeAndPassiveChecks'],
            name: 'scheduling-options',
            title: labelSchedulingOptions
          }
        ],
        name: 'monitoring-layout'
      }
    ]
  },
  {
    label: labelNotifications,
    nodes: [
      {
        className: twoColumnsFromWide,
        columnSeparator: true,
        columns: [
          'notificationRecipients',
          {
            className: stackedColumns,
            columns: ['notifyOn', 'notificationDelays'],
            name: 'notifications-when'
          }
        ],
        name: 'notifications-layout'
      }
    ]
  },
  {
    label: labelAdvanced,
    nodes: [
      {
        className: (count) =>
          count === 3 ? threeColumnsFromWide : twoColumnsFromWide,
        columnSeparator: true,
        columns: [
          'eventHandler',
          {
            className: stackedColumns,
            columns: ['freshness', 'acknowledgmentTimeout'],
            name: 'freshness-layout'
          },
          'flapping'
        ],
        name: 'advanced-layout'
      },
      'comment'
    ]
  }
];

// Every block the layout places, as often as it places it.
export const getPlacedBlockIds = (
  nodes: Array<LayoutNode> = layout.flatMap((section) => section.nodes)
): Array<BlockId> => getLayoutBlockIds(nodes);

// The sections the form shows, those left without any input aside: a mass
// change has no general information, a cloud platform no notification.
export const placeBlocks = ({
  blocks,
  t
}: {
  blocks: Blocks;
  t: TFunction;
}): Array<PlacedSection> =>
  placeLayoutBlocks({ blocks, idPrefix: hostResource.idPrefix, layout, t });
