import type { TFunction } from 'i18next';

import {
  schedulingColumns,
  stackedColumns,
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
  labelGeneralInformation,
  labelHostDetails,
  labelHostMonitoringProperties,
  labelMonitoringEngineSchedulingOptions,
  labelRelations,
  labelSchedulingOptions
} from '../translatedLabels';
import { type BlockId, type Blocks, hostTemplateResource } from './sections';

// The one place that says where each block is shown, as in the design. Every
// block is placed exactly once (`layout.test.ts`).
export const layout: Array<LayoutSection> = [
  { label: labelGeneralInformation, nodes: ['basicInformation'] },
  {
    label: labelHostMonitoringProperties,
    nodes: [
      {
        // The check command and its arguments on the left, the SNMP community
        // and its version on the right, with room above the macros.
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
            columns: ['linkedServiceTemplates'],
            name: 'relations-left'
          },
          {
            className: stackedColumns,
            columns: ['categories', 'severity'],
            name: 'relations-right'
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
        className: twoColumnsFromNarrow,
        columnSeparator: true,
        columns: [
          {
            className: twoColumnsFromWide,
            columns: [
              'notesAndIcons',
              {
                className: stackedColumns,
                columns: ['actionUrl', 'timezone'],
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
  placeLayoutBlocks({
    blocks,
    idPrefix: hostTemplateResource.idPrefix,
    layout,
    t
  });
