import { getServiceRowId, getSharedServiceIds, toDuration } from './utils';

describe('toDuration', () => {
  it('returns null for an interval nothing sets', () => {
    expect(toDuration(null, 60)).toBeNull();
    expect(toDuration(undefined, 60)).toBeNull();
  });

  it('converts to minutes when the duration falls on a whole minute', () => {
    expect(toDuration(5, 60)).toEqual({ count: 5, unit: 'min' });
    expect(toDuration(4, 30)).toEqual({ count: 2, unit: 'min' });
  });

  it('keeps seconds otherwise', () => {
    expect(toDuration(3, 45)).toEqual({ count: 135, unit: 'sec' });
    expect(toDuration(1, 30)).toEqual({ count: 30, unit: 'sec' });
  });

  it('shows a zero interval as zero minutes', () => {
    expect(toDuration(0, 60)).toEqual({ count: 0, unit: 'min' });
  });
});

describe('getServiceRowId', () => {
  it('tells apart the rows of a service linked to several hosts', () => {
    const row = { host: { id: 10, name: 'host A' }, id: 5, name: 'ping' };

    expect(getServiceRowId(row)).toBe('10_5');
    expect(getServiceRowId({ ...row, host: { id: 11, name: 'host B' } })).toBe(
      '11_5'
    );
  });
});

describe('getSharedServiceIds', () => {
  const ping = (hostId: number) => ({
    host: { id: hostId, name: `host ${hostId}` },
    id: 5,
    name: 'ping'
  });
  const cpu = { host: { id: 10, name: 'host 10' }, id: 0, name: 'cpu' };

  it('finds a selected service shown on several hosts, selected or not', () => {
    expect(getSharedServiceIds([ping(10), ping(11)], [])).toEqual([5]);
    expect(getSharedServiceIds([ping(10)], [ping(10), ping(11), cpu])).toEqual([
      5
    ]);
  });

  it('ignores a service shown on one host, and a shared one left unselected', () => {
    expect(getSharedServiceIds([cpu], [cpu, cpu])).toEqual([]);
    expect(getSharedServiceIds([cpu], [ping(10), ping(11)])).toEqual([]);
  });
});
