import { toDuration } from './utils';

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
