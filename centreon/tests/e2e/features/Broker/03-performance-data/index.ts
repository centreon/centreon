import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import {
  apiLatest,
  buildMetricQuery,
  buildMetricValuesQuery,
  buildRrdErrorCountCommand,
  buildRrdLastUpdateCommand,
  buildServiceIdsQuery,
  buildServiceStateQuery,
  buildSubmitTimedResultsCommand,
  metricName,
  metricThresholds,
  parseRrdLastUpdate,
  parseSubmittedTimes,
  passiveHost,
  passiveService,
  perfdataOf
} from '../common';

interface SubmittedValue {
  output: string;
  time: number;
  value: number;
}

interface GraphMetric {
  data: Array<number | null>;
  metric: string;
}

const values = [42, 43, 44];
// Check times on boundaries of the RRD step (1 or 5 minutes): each value
// fills whole steps of the RRD file, so the graph can show it
const rrdStepAlignment = 300;
const brokerReloadWaitingTime = 15_000;
const pollingOptions = { interval: 5_000, timeout: 180_000 };

let submitted: Array<SubmittedValue> = [];
let metricId = 0;

beforeEach(() => {
  cy.startContainers();

  submitted = [];
  metricId = 0;

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
});

afterEach(() => {
  cy.stopContainers();
});

const lastSubmitted = (): SubmittedValue => submitted[submitted.length - 1];

Given('a passive service is exported and listed in Resources Status', () => {
  cy.addHost({
    activeCheckEnabled: false,
    address: '127.0.0.1',
    name: passiveHost,
    template: 'generic-host'
  });
  cy.addService({
    activeCheckEnabled: false,
    host: passiveHost,
    maxCheckAttempts: 1,
    name: passiveService,
    template: 'Ping-LAN'
  });
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'SETPARAM',
      object: 'SERVICE',
      values: `${passiveHost};${passiveService};normal_check_interval;1`
    }
  });
  cy.applyPollerConfiguration();

  checkHostsAreMonitored([{ name: passiveHost }]);
  checkServicesAreMonitored([{ name: passiveService }]);
  cy.wait(brokerReloadWaitingTime);

  cy.loginAsAdminViaApiV2();
  cy.request({
    method: 'GET',
    url: `${INTERCEPTORS.api.monitor_resources}?page=1&limit=30&search=${encodeURIComponent(
      JSON.stringify({
        $and: [
          { name: { $eq: passiveService } },
          { parent_name: { $eq: passiveHost } }
        ]
      })
    )}`
  }).then(({ body }) => {
    expect(body.result, `${passiveService} in Resources Status`).to.have.length(
      1
    );
  });
});

When('check results with performance data are submitted for it', () => {
  cy.execInContainer({ command: 'date +%s', name: 'web' }).then(
    ({ output }) => {
      const now = Number.parseInt(output.trim(), 10);
      const lastBoundary =
        Math.floor(now / rrdStepAlignment) * rrdStepAlignment;

      submitted = values.map((value, index) => ({
        output: `rbt perf ${index + 1}`,
        time: lastBoundary - (values.length - index) * rrdStepAlignment,
        value
      }));

      cy.execInContainer({
        command: buildSubmitTimedResultsCommand(
          passiveHost,
          passiveService,
          submitted.map(({ output, time, value }) => ({
            output: `${output}|${perfdataOf(value)}`,
            status: 0,
            time
          }))
        ),
        name: 'web'
      }).then(({ output: written }) => {
        expect(parseSubmittedTimes(written)).to.deep.eq(
          submitted.map(({ time }) => time)
        );
      });
    }
  );

  // The Engine processed the last one
  cy.waitUntil(
    () =>
      cy
        .requestOnDatabase({
          database: 'centreon_storage',
          query: buildServiceStateQuery(passiveHost, passiveService)
        })
        .then(
          ([rows]) =>
            rows.length === 1 && rows[0].output === lastSubmitted().output
        ),
    {
      ...pollingOptions,
      errorMsg: `${passiveService} did not get the submitted results`
    }
  );
  cy.requestOnDatabase({
    database: 'centreon_storage',
    query: buildServiceStateQuery(passiveHost, passiveService)
  }).then(([rows]) => {
    expect(Number(rows[0].state), 'state').to.eq(0);
    expect(rows[0].perfdata, 'perfdata').to.eq(
      perfdataOf(lastSubmitted().value)
    );
  });
});

Then(
  'the Broker creates the service index and its metric with the thresholds of the performance data',
  () => {
    cy.waitUntil(
      () =>
        cy
          .requestOnDatabase({
            database: 'centreon_storage',
            query: buildMetricQuery(passiveHost, passiveService, metricName)
          })
          .then(([rows]) => rows.length > 0),
      {
        ...pollingOptions,
        errorMsg: `No metric ${metricName} for ${passiveService}`
      }
    );

    cy.requestOnDatabase({
      database: 'centreon_storage',
      query: buildMetricQuery(passiveHost, passiveService, metricName)
    }).then(([rows]) => {
      expect(rows, `index and metric ${metricName}`).to.have.length(1);

      const [{ crit, index_id: indexId, max, metric_id: id, min, warn }] = rows;

      expect(Number(indexId), 'service index').to.be.greaterThan(0);
      expect(Number(warn), 'warning threshold').to.eq(metricThresholds.warning);
      expect(Number(crit), 'critical threshold').to.eq(
        metricThresholds.critical
      );
      expect(Number(min), 'minimum').to.eq(metricThresholds.min);
      expect(Number(max), 'maximum').to.eq(metricThresholds.max);

      metricId = Number(id);
    });
  }
);

Then(
  'every value is stored in the performance data table at its check time',
  () => {
    const query = (): string =>
      buildMetricValuesQuery(metricId, submitted[0].time);

    cy.waitUntil(
      () =>
        cy
          .requestOnDatabase({ database: 'centreon_storage', query: query() })
          .then(([rows]) => rows.length >= submitted.length),
      { ...pollingOptions, errorMsg: 'The values are not all in data_bin' }
    );
    cy.requestOnDatabase({ database: 'centreon_storage', query: query() }).then(
      ([rows]) => {
        expect(
          rows.map(({ ctime, value }) => [Number(ctime), Number(value)]),
          'data_bin rows of the metric'
        ).to.deep.eq(submitted.map(({ time, value }) => [time, value]));
      }
    );
  }
);

Then(
  'the RRD Broker updates the metric RRD file with the last value at the same time',
  () => {
    cy.waitUntil(
      () =>
        cy
          .execInContainer(
            {
              command: `${buildRrdLastUpdateCommand(metricId)} || true`,
              name: 'web'
            },
            { log: false }
          )
          .then(
            ({ output }) =>
              parseRrdLastUpdate(output)?.time === lastSubmitted().time
          ),
      {
        ...pollingOptions,
        errorMsg: `${metricId}.rrd is not updated with the last value`
      }
    );

    cy.execInContainer({
      command: buildRrdLastUpdateCommand(metricId),
      name: 'web'
    }).then(({ output }) => {
      const lastUpdate = parseRrdLastUpdate(output);

      expect(lastUpdate?.owner, 'owner of the RRD file').to.eq(
        'centreon-broker'
      );
      expect(lastUpdate?.time, 'last update time').to.eq(lastSubmitted().time);
      expect(lastUpdate?.value, 'last value').to.eq(lastSubmitted().value);
    });
  }
);

Then('the RRD Broker log has no error for this file', () => {
  cy.execInContainer({
    command: buildRrdErrorCountCommand(metricId),
    name: 'web'
  }).then(({ output }) => {
    expect(
      Number.parseInt(output.trim(), 10),
      `errors about ${metricId}.rrd`
    ).to.eq(0);
  });
});

Then('the graph of the service shows the submitted values', () => {
  const lowest = Math.min(...values);
  const highest = Math.max(...values);

  cy.requestOnDatabase({
    database: 'centreon_storage',
    query: buildServiceIdsQuery(passiveHost, passiveService)
  }).then(([rows]) => {
    expect(rows, `ids of ${passiveService}`).to.have.length(1);

    const [{ host_id: hostId, service_id: serviceId }] = rows;
    const start = new Date(
      (submitted[0].time - rrdStepAlignment) * 1000
    ).toISOString();
    const end = new Date(
      (lastSubmitted().time + rrdStepAlignment) * 1000
    ).toISOString();

    // The request the graph of the resource details panel sends
    cy.request({
      method: 'GET',
      url: `${apiLatest}/monitoring/hosts/${hostId}/services/${serviceId}/metrics/performance?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`
    }).then(({ body, status }) => {
      expect(status).to.eq(200);

      const metric = (body.metrics as Array<GraphMetric>).find(
        ({ metric: name }) => name === metricName
      );

      expect(metric, `curve ${metricName}`).to.not.eq(undefined);

      const points = (metric?.data ?? []).filter(
        (point): point is number => point !== null
      );

      expect(points.length, `points of ${metricName}`).to.be.greaterThan(0);
      points.forEach((point) => {
        expect(point, `point of ${metricName}`).to.be.within(lowest, highest);
      });
    });
  });
});
