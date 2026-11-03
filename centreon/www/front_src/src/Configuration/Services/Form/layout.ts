import type { TFunction } from 'i18next';

import {
  schedulingColumns,
  stackedColumns,
  threeColumnsFromWide,
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
  labelCheckCommandTemplateCustomMacros,
  labelGeneralInformation,
  labelMonitoringEngine,
  labelMonitoringEngineSchedulingOptions,
  labelNotification,
  labelRelations,
  labelSchedulingOptions
} from '../translatedLabels';
import { type BlockId, type Blocks, serviceResource } from './sections';

// The one place that says where each block is shown, as in the design. Every
// block is placed exactly once (`layout.test.ts`).
export const layout: Array<LayoutSection> = [
  { label: labelGeneralInformation, nodes: ['basicInformation'] },
  {
    label: labelCheckCommandTemplateCustomMacros,
    nodes: [
      {
        // The command on the left, over the template; the macros on the
        // right.
        className: twoColumnsFromWide,
        columnSeparator: true,
        columns: [
          {
            className: stackedColumns,
            columns: ['checkCommand', 'templates'],
            name: 'check-command-template'
          },
          'macros'
        ],
        name: 'check-command-layout'
      }
    ]
  },
  {
    label: labelRelations,
    nodes: [
      {
        // The service groups and traps on the left, the classification of
        // the service on the right.
        className: twoColumnsFromNarrow,
        columnSeparator: true,
        columns: [
          {
            className: stackedColumns,
            columns: ['groups', 'snmpTraps'],
            name: 'relations-groups'
          },
          {
            // The right column: what classifies the service.
            className: stackedColumns,
            columns: ['categories', 'severity', 'graphTemplate'],
            name: 'relations-classification'
          }
        ],
        name: 'relations-layout'
      }
    ]
  },
  {
    label: labelMonitoringEngineSchedulingOptions,
    nodes: [
      {
        // Note, note URL, icon and alt icon | action URL and coordinates.
        className: twoColumnsFromNarrow,
        columns: ['notesAndIcons', 'actionUrlAndGeoCoordinates'],
        name: 'monitoring-engine',
        title: labelMonitoringEngine
      },
      {
        // The check numbers, then the Yes / No / Default buttons, which a
        // cloud platform leaves out.
        className: schedulingColumns,
        columnSeparator: true,
        columns: ['checkPeriodAndIntervals', 'activeAndPassiveChecks'],
        name: 'scheduling-options',
        title: labelSchedulingOptions
      }
    ]
  },
  {
    // Absent on cloud, where no notification block is available.
    label: labelNotification,
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
        // Auto-remediation | freshness and acknowledgement | flapping: the
        // cloud keeps the first two only.
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
      // Not in the design, on-premise only: below, full width.
      'obsessRetainAndStalking',
      // Full width, under the other advanced fields.
      'comment'
    ]
  }
];

// Every block the layout places, as often as it places it.
export const getPlacedBlockIds = (
  nodes: Array<LayoutNode> = layout.flatMap((section) => section.nodes)
): Array<BlockId> => getLayoutBlockIds(nodes);

// The sections the form shows, those left without any input aside.
export const placeBlocks = ({
  blocks,
  t
}: {
  blocks: Blocks;
  t: TFunction;
}): Array<PlacedSection> =>
  placeLayoutBlocks({ blocks, idPrefix: serviceResource.idPrefix, layout, t });
