import type { TFunction } from 'i18next';

import { labelGeneralInformation } from '../translatedLabels';
import { getPlacedBlockIds, placeBlocks } from './layout';
import {
  getAvailableSections,
  getBlocks,
  type SectionContext
} from './sections';

const t = ((label: string): string => label) as TFunction;

// Every form the platform can build. A host template has no mass change.
const contexts: Array<[name: string, context: SectionContext]> = [
  ['onPrem', { isCloudPlatform: false, t }],
  [
    'onPrem with additive inheritance',
    { isAdditiveInheritanceEnabled: true, isCloudPlatform: false, t }
  ],
  ['cloud', { isCloudPlatform: true, t }]
];

const getSectionLabels = (context: SectionContext): Array<string> =>
  placeBlocks({ blocks: getBlocks(context), t }).map(({ label }) => label);

describe('layout', () => {
  it('places every block once', () => {
    const placed = getPlacedBlockIds();

    expect(new Set(placed).size).toBe(placed.length);
  });

  it('places nothing no section hands over', () => {
    const handedOver = new Set(
      contexts.flatMap(([, context]) => Object.keys(getBlocks(context)))
    );

    expect(getPlacedBlockIds().filter((id) => !handedOver.has(id))).toEqual([]);
  });

  it.each(contexts)(
    'places every block the sections hand over (%s)',
    (_name, context) => {
      const placed = new Set<string>(getPlacedBlockIds());
      const blockIds = getAvailableSections(context).flatMap(({ section }) =>
        Object.keys(section.getInputs(context))
      );

      // Merged into one object, a block two sections named would hide one.
      expect(new Set(blockIds).size).toBe(blockIds.length);
      expect(blockIds.filter((id) => !placed.has(id))).toEqual([]);
    }
  );

  it('opens on the general information', () => {
    expect(getSectionLabels({ isCloudPlatform: false, t })).toEqual([
      labelGeneralInformation
    ]);
    expect(getSectionLabels({ isCloudPlatform: true, t })).toEqual([
      labelGeneralInformation
    ]);
  });
});
