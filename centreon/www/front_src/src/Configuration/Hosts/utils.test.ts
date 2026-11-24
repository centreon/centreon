import { getHostFormPath } from './utils';

describe('getHostFormPath', () => {
  it('opens the host form in the Hosts page', () => {
    expect(getHostFormPath(10)).toBe('/configuration/hosts?mode=edit&id=10');
  });
});
