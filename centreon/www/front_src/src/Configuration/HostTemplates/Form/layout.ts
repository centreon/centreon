import type { TFunction } from 'i18next';

import { twoColumns, twoColumnsFromWide } from '../../ResourceForm/grid';
import {
  getPlacedBlockIds as getLayoutBlockIds,
  type LayoutNode,
  type LayoutSection,
  type PlacedSection,
  placeBlocks as placeLayoutBlocks
} from '../../ResourceForm/layout';
import {
  labelGeneralInformation,
  labelHostMonitoringProperties
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
