import type { TFunction } from 'i18next';

import { schedulingColumns } from '../../ResourceForm/grid';
import {
  getPlacedBlockIds as getLayoutBlockIds,
  type LayoutNode,
  type LayoutSection,
  type PlacedSection,
  placeBlocks as placeLayoutBlocks
} from '../../ResourceForm/layout';
import {
  labelCheckCommandTemplateCustomMacros,
  labelGeneralInformation,
  labelMonitoringEngineSchedulingOptions,
  labelSchedulingOptions
} from '../translatedLabels';
import { type BlockId, type Blocks, serviceResource } from './sections';

// The one place that says where each block is shown, as in the design. Every
// block is placed exactly once (`layout.test.ts`).
export const layout: Array<LayoutSection> = [
  { label: labelGeneralInformation, nodes: ['basicInformation'] },
  { label: labelCheckCommandTemplateCustomMacros, nodes: ['templates'] },
  {
    label: labelMonitoringEngineSchedulingOptions,
    nodes: [
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
