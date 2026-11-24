import { Resource } from '../models';
import { maxCorrelatedResources, selectMostSevere, truncate } from './utils';

const resource = (name: string, severityCode?: number): Resource =>
  ({
    name,
    status:
      severityCode === undefined ? undefined : { severity_code: severityCode }
  }) as unknown as Resource;

const names = (resources: Array<Resource>): Array<string> =>
  resources.map(({ name }) => name);

describe('truncate', () => {
  it('passes null and undefined through as null', () => {
    expect(truncate(null)).toBeNull();
    expect(truncate(undefined)).toBeNull();
  });

  it('keeps a value that fits', () => {
    expect(truncate('short', 5)).toBe('short');
  });

  it('does not cut an emoji in half at the boundary', () => {
    // the emoji is two UTF-16 units: a cut by unit would keep half of it
    const cut = truncate('ab😀cd', 3) as string;

    expect(cut).toBe('ab😀');
    // no lone surrogate left
    expect(cut).not.toMatch(
      /[\uD800-\uDBFF](?![\uDC00-\uDFFF])|(?<![\uD800-\uDBFF])[\uDC00-\uDFFF]/
    );
  });

  it('counts an emoji as one character', () => {
    expect(truncate('😀😀😀', 2)).toBe('😀😀');
  });
});

describe('selectMostSevere', () => {
  it('keeps the resources as they are when they fit', () => {
    const resources = [resource('ok', 5), resource('critical', 1)];

    expect(selectMostSevere(resources)).toBe(resources);
  });

  it('keeps the most severe resources only, up to the cap', () => {
    const resources = [
      ...Array.from({ length: maxCorrelatedResources }, (_, index) =>
        resource(`ok-${index}`, 5)
      ),
      resource('critical', 1)
    ];

    const selected = selectMostSevere(resources);

    expect(selected).toHaveLength(maxCorrelatedResources);
    expect(selected[0].name).toBe('critical');
    expect(names(selected)).not.toContain(`ok-${maxCorrelatedResources - 1}`);
  });

  it('sorts a resource without status last', () => {
    const resources = [
      resource('no-status'),
      ...Array.from({ length: maxCorrelatedResources }, (_, index) =>
        resource(`warning-${index}`, 2)
      )
    ];

    expect(names(selectMostSevere(resources))).not.toContain('no-status');
  });

  it('keeps the given order within a status', () => {
    const resources = [
      resource('ok-first', 5),
      resource('critical-first', 1),
      ...Array.from({ length: maxCorrelatedResources - 2 }, (_, index) =>
        resource(`ok-${index}`, 5)
      ),
      resource('critical-second', 1)
    ];

    expect(names(selectMostSevere(resources)).slice(0, 3)).toEqual([
      'critical-first',
      'critical-second',
      'ok-first'
    ]);
  });
});
