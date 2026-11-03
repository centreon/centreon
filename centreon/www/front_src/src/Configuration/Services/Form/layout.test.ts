import type { TFunction } from 'i18next';

import {
  labelCheckCommandTemplateCustomMacros,
  labelGeneralInformation,
  labelMonitoringEngineSchedulingOptions,
  labelRelations
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

  it('opens on the general information, then the check command', () => {
    expect(getSectionLabels({ isCloudPlatform: false, t }).slice(0, 2)).toEqual(
      [labelGeneralInformation, labelCheckCommandTemplateCustomMacros]
    );
    expect(getSectionLabels({ isCloudPlatform: true, t }).slice(0, 2)).toEqual([
      labelGeneralInformation,
      labelCheckCommandTemplateCustomMacros
    ]);
  });

  it('places the macros next to the check command', () => {
    const [, checkCommandSection] = placeBlocks({
      blocks: getBlocks({ isCloudPlatform: false, t }),
      t
    });

    const [left, macros] = checkCommandSection?.inputs[0]?.grid?.columns ?? [];

    // The command over the template on the left, the macros on the right.
    expect(left?.grid?.columns.map(({ fieldName }) => fieldName)).toEqual([
      'check-options',
      'template'
    ]);
    expect(macros?.fieldName).toBe('checkOptions.macros');
  });

  it('keeps the check command but not the macros in a mass change', () => {
    const blocks = getBlocks({
      isCloudPlatform: false,
      isMassChange: true,
      t
    });

    expect(blocks.checkCommand).toBeDefined();
    expect(blocks.macros).toBeUndefined();
  });

  it('shows the relations after the check command', () => {
    const labels = getSectionLabels({ isCloudPlatform: false, t });

    expect(labels.indexOf(labelRelations)).toBe(
      labels.indexOf(labelCheckCommandTemplateCustomMacros) + 1
    );
  });

  it('keeps the service groups on cloud and the traps on-premise only', () => {
    expect(Object.keys(getBlocks({ isCloudPlatform: false, t }))).toEqual(
      expect.arrayContaining(['groups', 'snmpTraps'])
    );

    const cloudBlocks = Object.keys(getBlocks({ isCloudPlatform: true, t }));

    expect(cloudBlocks).toContain('groups');
    expect(cloudBlocks).not.toContain('snmpTraps');
  });

  it('has no general information in a mass change', () => {
    expect(
      getSectionLabels({ isCloudPlatform: false, isMassChange: true, t })
    ).not.toContain(labelGeneralInformation);
  });

  it('places the template in the check command section', () => {
    const section = layout.find(
      ({ label }) => label === labelCheckCommandTemplateCustomMacros
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
