/**
 * Test data for the Additional Connector Configuration (ACC) feature.
 *
 * Mirrors `centreon/tests/e2e/fixtures/additional-configurations/acc.json`
 * from the Cypress suite, but uses `pw-` prefixed, unique-ish names so reruns
 * stay idempotent (the spec deletes whatever it creates afterwards).
 *
 * The poller is the built-in "Central" so no extra poller has to be
 * provisioned through CLAPI (the Cypress suite seeded Poller-1/2/3, which the
 * admin-driven happy paths do not require).
 */
export interface AdditionalConnectorSeed {
  /** Connector name shown in the listing. */
  name: string;
  /** Connector type, the only one currently shipped ("VMWare 6/7"). */
  type: string;
  /** Poller name to attach the connector to. */
  poller: string;
  /** vCenter/ESX parameter group values. */
  username: string;
  password: string;
  vCenterName: string;
  url: string;
  /** Default port pre-filled by the form for the VMWare type. */
  port: string;
}

export const connectorType = 'VMWare 6/7';

export const additionalConnector: AdditionalConnectorSeed = {
  name: 'pw-Connector-001',
  password: 'Abcde!2021',
  poller: 'Central',
  port: '5700',
  type: connectorType,
  url: 'https://10.0.0.0/sdk',
  username: 'admin',
  vCenterName: 'vCenter-001'
};

export const updatedAdditionalConnector: AdditionalConnectorSeed = {
  name: 'pw-Connector-002',
  password: 'Abcde!202',
  poller: 'Central',
  port: '6900',
  type: connectorType,
  url: 'https://10.0.1.3/sdk',
  username: 'admin-updated',
  vCenterName: 'vCenter-00'
};
