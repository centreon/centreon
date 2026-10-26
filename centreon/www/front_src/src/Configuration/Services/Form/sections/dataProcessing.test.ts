import { dataProcessing } from './dataProcessing';

const values = {
  dataProcessing: {
    ...(dataProcessing.defaultValues.dataProcessing as object),
    checkFreshness: 'false',
    freshnessThreshold: 30
  }
};

// The check command (F04) is not in the form yet: these cases stand for the
// value its selector will hold.
describe('dataProcessing payload', () => {
  it('keeps the freshness settings with a regular check command', () => {
    const payload = dataProcessing.toPayload(
      { ...values, checkCommand: { id: 1, name: 'check-ping' } },
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
      const payload = dataProcessing.toPayload(
        {
          ...values,
          checkCommand: { id: 2, isCentreonMonitoringAgent: true, name: 'cma' }
        },
        { isCloudPlatform }
      );

      expect(payload.data_processing).toMatchObject({
        check_freshness: 'true',
        freshness_threshold: 500
      });
    }
  );

  it('leaves the on-premise fields out on a cloud platform', () => {
    const payload = dataProcessing.toPayload(values, { isCloudPlatform: true });

    expect(Object.keys(payload.data_processing as object).sort()).toEqual([
      'check_freshness',
      'event_handler_command_id',
      'event_handler_enabled',
      'freshness_threshold'
    ]);
  });
});
