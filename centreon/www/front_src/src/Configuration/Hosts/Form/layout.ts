import type { InputPropsWithoutGroup } from '@centreon/ui';

import type { TFunction } from 'i18next';

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
import {
  getBlock,
  schedulingColumns,
  stackedColumns,
  threeColumnsFromWide,
  twoColumns,
  twoColumnsFromNarrow,
  twoColumnsFromWide
} from './grid';
import type { BlockId, Blocks } from './sections';

// Blocks laid out in columns, under an optional untranslated title. The class
// may depend on how many of the columns the platform keeps.
interface LayoutGrid {
  className: string | ((count: number) => string);
  // A dashed line between its columns, as in the design.
  columnSeparator?: boolean;
  columns: Array<LayoutNode>;
  name: string;
  title?: string;
}

type LayoutNode = BlockId | LayoutGrid;

interface LayoutSection {
  // Untranslated: it is also the group name.
  label: string;
  nodes: Array<LayoutNode>;
}

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
              'hostDetailsLeft',
              {
                className: stackedColumns,
                columns: ['hostDetailsRight', 'timezone', 'massChangePoller'],
                name: 'host-details-right'
              }
            ],
            name: 'host-details',
            title: labelHostDetails
          },
          {
            className: schedulingColumns,
            columnSeparator: true,
            columns: ['schedulingNumbers', 'schedulingChecks'],
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

const isBlockId = (node: LayoutNode): node is BlockId =>
  typeof node === 'string';

// Every block the layout places, as often as it places it.
export const getPlacedBlockIds = (
  nodes: Array<LayoutNode> = layout.flatMap((section) => section.nodes)
): Array<BlockId> =>
  nodes.flatMap((node) =>
    isBlockId(node) ? [node] : getPlacedBlockIds(node.columns)
  );

// A block or a grid, or nothing once the platform left out all it holds.
const placeNode = ({
  blocks,
  node,
  t
}: {
  blocks: Blocks;
  node: LayoutNode;
  t: TFunction;
}): InputPropsWithoutGroup | null => {
  if (isBlockId(node)) {
    return blocks[node] ?? null;
  }

  const columns = node.columns
    .map((column) => placeNode({ blocks, node: column, t }))
    .filter((column): column is InputPropsWithoutGroup => column !== null);

  if (columns.length === 0) {
    return null;
  }

  const className =
    typeof node.className === 'function'
      ? node.className(columns.length)
      : node.className;

  return getBlock({
    // A column left alone takes the whole width.
    className: columns.length === 1 ? stackedColumns : className,
    columnSeparator: node.columnSeparator,
    columns,
    name: node.name,
    title: node.title && t(node.title)
  });
};

export interface PlacedSection {
  inputs: Array<InputPropsWithoutGroup>;
  label: string;
  order: number;
}

// The sections the form shows, those left without any input aside: a mass
// change has no general information, a cloud platform no notification.
export const placeBlocks = ({
  blocks,
  t
}: {
  blocks: Blocks;
  t: TFunction;
}): Array<PlacedSection> =>
  layout
    .map(({ label, nodes }, index) => ({
      inputs: nodes
        .map((node) => placeNode({ blocks, node, t }))
        .filter((input): input is InputPropsWithoutGroup => input !== null),
      label,
      order: index + 1
    }))
    .filter(({ inputs }) => inputs.length > 0);
