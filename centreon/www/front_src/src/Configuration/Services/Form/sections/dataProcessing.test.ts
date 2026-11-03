import { getDefaultValues, getPayload } from '../../../ResourceForm/blocks';
import { blocks, type FormValues } from '.';

const getValues = (checkOptions: unknown): FormValues => {
  const defaultValues = getDefaultValues(blocks, { isCloudPlatform: false });

  return {
    ...defaultValues,
    // The check command block's value, next to the macros.
    checkOptions: {
      ...(defaultValues.checkOptions as object),
      ...(checkOptions as object)
    },
    dataProcessing: {
      ...(defaultValues.dataProcessing as object),
      checkFreshness: 'false',
      freshnessThreshold: 30
    }
  };
};

// Each case passes `getValues` what the check command selector holds.
describe('data processing payload', () => {
  it('keeps the freshness settings with a regular check command', () => {
    const payload = getPayload(
      blocks,
      getValues({ args: '', command: { id: 1, name: 'check-ping' } }),
      { isCloudPlatform: false }
    );

    expect(payload.data_processing).toMatchObject({
      check_freshness: 'false',
      freshness_threshold: 30
    });
  });

  it.each([false, true])(
    'forces the freshness settings of a Centreon Monitoring Agent command (cloud: %s)',
    (isCloudPlatform) => {
      const payload = getPayload(
        blocks,
        getValues({
          args: '',
          command: { id: 2, isCentreonMonitoringAgent: true, name: 'cma' }
        }),
        { isCloudPlatform }
      );

      expect(payload.data_processing).toMatchObject({
        check_freshness: 'true',
        freshness_threshold: 500
      });
    }
  );

  it('leaves the on-premise fields out on a cloud platform', () => {
    const payload = getPayload(blocks, getValues({ args: '', command: null }), {
      isCloudPlatform: true
    });

    expect(Object.keys(payload.data_processing as object).sort()).toEqual([
      'check_freshness',
      'event_handler_command_id',
      'event_handler_enabled',
      'freshness_threshold'
    ]);
  });
});
