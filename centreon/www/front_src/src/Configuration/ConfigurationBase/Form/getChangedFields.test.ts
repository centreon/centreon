import { getChangedFields } from './getChangedFields';

describe('getChangedFields', () => {
  it('keeps only the keys whose value differs', () => {
    expect(
      getChangedFields(
        { alias: 'web', name: 'srv', timezone_id: null },
        { alias: null, name: 'srv', timezone_id: null }
      )
    ).toEqual({ alias: 'web' });
  });

  it('compares nested objects key by key and drops one left unchanged', () => {
    expect(
      getChangedFields(
        {
          check_options: { args: [], command_id: null },
          scheduling_options: { max_check_attempts: 3, retry: null }
        },
        {
          check_options: { args: [], command_id: null },
          scheduling_options: { max_check_attempts: null, retry: null }
        }
      )
    ).toEqual({ scheduling_options: { max_check_attempts: 3 } });
  });

  it('compares lists whole', () => {
    expect(
      getChangedFields(
        { category_ids: [1, 2], host_group_ids: [] },
        { category_ids: [], host_group_ids: [] }
      )
    ).toEqual({ category_ids: [1, 2] });
  });

  // Legacy's `empty()` dropped them: a falsy value is a value.
  it('sends a zero or false that differs from the untouched value', () => {
    expect(
      getChangedFields(
        { enabled: false, first_notification_delay: 0 },
        { enabled: null, first_notification_delay: null }
      )
    ).toEqual({ enabled: false, first_notification_delay: 0 });
  });

  it('returns nothing for an untouched payload', () => {
    const payload = { notifications: { contacts: [], options: [] } };

    expect(getChangedFields(payload, payload)).toEqual({});
  });
});
