import type { TelegrafAgentSeed } from '../pages/AgentConfigurationPage';

/**
 * Telegraf agent configuration seeds for the Playwright `agent-configuration`
 * spec, adapted from the Cypress `agents-configuration/agent-config.json`
 * fixture. Names are prefixed with `pw-` so reruns stay idempotent and never
 * collide with objects created by the Cypress suite.
 *
 * The built-in `Central` poller is used so no extra poller has to be
 * provisioned via CLAPI.
 */
export const telegrafAgent: TelegrafAgentSeed = {
  caFileName: 'pw-ca-file-name-001.crt',
  certificateFileName: 'pw-certificate-name-001.crt',
  name: 'pw-telegraf-001',
  pollerName: 'Central',
  privateKeyFileName: 'pw-private-key-name-001.key',
  publicCertificateFileName: 'pw-public-certificate-name-001.crt'
};
