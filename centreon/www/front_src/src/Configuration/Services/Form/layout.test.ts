import type { TFunction } from 'i18next';

import {
  labelGeneralInformation,
  labelMonitoringEngineSchedulingOptions,
  labelServiceMonitoringProperties
} from '../translatedLabels';
import { getPlacedBlockIds, layout, placeBlocks } from './layout';
import {
  getAvailableSections,
  getBlocks,
  type SectionContext
} from './sections';

const t = ((label: string): string => label) as TFunction;

// Every form the platform and the mass change can build.
const contexts: Array<[name: string, context: SectionContext]> = [
  ['onPrem', { isCloudPlatform: false, t }],
  [
    'onPrem with additive inheritance',
    { isAdditiveInheritanceEnabled: true, isCloudPlatform: false, t }
  ],
  ['cloud', { isCloudPlatform: true, t }],
  ['onPrem mass change', { isCloudPlatform: false, isMassChange: true, t }],
  ['cloud mass change', { isCloudPlatform: true, isMassChange: true, t }]
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
    expect(getSectionLabels({ isCloudPlatform: false, t })[0]).toBe(
      labelGeneralInformation
    );
    expect(getSectionLabels({ isCloudPlatform: true, t })[0]).toBe(
      labelGeneralInformation
    );
  });

  it('has no general information in a mass change', () => {
    expect(
      getSectionLabels({ isCloudPlatform: false, isMassChange: true, t })
    ).not.toContain(labelGeneralInformation);
  });

  it('places the template in the check command section', () => {
    const section = layout.find(
      ({ label }) => label === labelServiceMonitoringProperties
    );

    expect(getPlacedBlockIds(section?.nodes)).toContain('templates');
  });

  it('places the scheduling options in the monitoring engine section', () => {
    const section = layout.find(
      ({ label }) => label === labelMonitoringEngineSchedulingOptions
    );

    expect(getPlacedBlockIds(section?.nodes)).toEqual(
      expect.arrayContaining([
        'checkPeriodAndIntervals',
        'activeAndPassiveChecks'
      ])
    );
  });

  it('keeps the scheduling numbers but no check buttons on a cloud platform', () => {
    expect(Object.keys(getBlocks({ isCloudPlatform: true, t }))).toContain(
      'checkPeriodAndIntervals'
    );
    expect(Object.keys(getBlocks({ isCloudPlatform: true, t }))).not.toContain(
      'activeAndPassiveChecks'
    );
  });

  it('has no template in a mass change', () => {
    expect(
      Object.keys(getBlocks({ isCloudPlatform: false, isMassChange: true, t }))
    ).not.toContain('templates');
  });
});
