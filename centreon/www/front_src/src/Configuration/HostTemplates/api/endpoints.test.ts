import type { SearchParameter } from '@centreon/ui';

import { getParentTemplatesEndpoint } from './endpoints';

const getParameters = (search?: SearchParameter): URLSearchParams =>
  new URLSearchParams(
    getParentTemplatesEndpoint(null)({ page: 1, search }).split('?')[1]
  );

const selected = { field: 'name', values: { $ni: ['generic'] } };

describe('getParentTemplatesEndpoint', () => {
  it('sends the typed text as name[lk]', () => {
    const parameters = getParameters({
      conditions: [selected, { field: 'name', values: { $lk: '%lin%' } }]
    });

    expect(parameters.get('name[lk]')).toBe('lin');
  });

  it('sends no name[lk] without a typed text', () => {
    expect(getParameters({ conditions: [selected] }).has('name[lk]')).toBe(
      false
    );
    expect(getParameters().has('name[lk]')).toBe(false);
  });
});
