import type { Page } from '@playwright/test';

import { expect, test } from './fixtures';

// Migrated from AgentConfiguration/Form/useValidationSchema: a hook spec,
// driven through the validation function its story exposes on window.

const secure = { id: 'secure', name: 'Secure' };
const poller = [{ id: 1, name: 'poller1' }];
const telegraf = (configuration: Record<string, unknown>) => ({
  configuration: { confServerPort: 8080, ...configuration },
  connectionMode: secure,
  name: 'test',
  pollers: poller,
  type: { id: 'telegraf' }
});
const token = {
  creatorId: 1,
  id: '1',
  name: 'token1',
  token_name: 'test-token'
};
const cmaHost = (address: string) => ({
  address,
  pollerCaCertificate: null,
  pollerCaName: null,
  port: 4317,
  token
});
const cma = (configuration: Record<string, unknown>) => ({
  configuration,
  connectionMode: secure,
  name: 'test',
  pollers: poller,
  type: { id: 'centreon-agent' }
});
const secureContext = { connectionMode: { id: 'secure' } };
const cmaContext = (configuration: Record<string, unknown>) => ({
  configuration,
  connectionMode: { id: 'secure' },
  type: { id: 'centreon-agent' }
});

const validate = async (
  page: Page,
  values: unknown,
  context: Record<string, unknown>
): Promise<boolean> => {
  await page.waitForFunction(() => Boolean(window.validateAgentConfiguration));

  return page.evaluate(
    async ([valuesArg, contextArg]) =>
      (await window.validateAgentConfiguration?.(valuesArg, contextArg))
        ?.valid ?? false,
    [values, context] as const
  );
};

const cases = [
  {
    context: secureContext,
    name: 'accepts a .crt certificate',
    valid: true,
    values: telegraf({ otelPublicCertificate: '/path/to/cert.crt' })
  },
  {
    context: secureContext,
    name: 'accepts a .cert certificate',
    valid: true,
    values: telegraf({ otelPublicCertificate: 'cert.cert' })
  },
  {
    context: secureContext,
    name: 'accepts a .key private key',
    valid: true,
    values: telegraf({ otelPrivateKey: '/path/to/key.key' })
  },
  {
    context: secureContext,
    name: 'rejects another certificate extension',
    valid: false,
    values: telegraf({ otelPublicCertificate: '/path/to/cert.txt' })
  },
  {
    context: secureContext,
    name: 'rejects a relative certificate path',
    valid: false,
    values: {
      configuration: {
        confServerPort: 8080,
        otelPublicCertificate: './path/to/cert.cert'
      }
    }
  },
  {
    context: secureContext,
    name: 'accepts a valid port',
    valid: true,
    values: telegraf({ confServerPort: 443 })
  },
  {
    context: secureContext,
    name: 'rejects port 0',
    valid: false,
    values: telegraf({ confServerPort: 0 })
  },
  {
    context: cmaContext({ agentInitiated: true }),
    name: 'accepts an agent-initiated CMA',
    valid: true,
    values: cma({
      agentInitiated: true,
      hosts: [],
      pollerInitiated: false,
      port: 4317,
      tokens: [{ creatorId: 1, id: '1', name: 'token1' }]
    })
  },
  {
    context: cmaContext({ pollerInitiated: true }),
    name: 'accepts a poller-initiated CMA',
    valid: true,
    values: cma({
      agentInitiated: false,
      hosts: [cmaHost('192.168.1.100')],
      pollerInitiated: true,
      port: null,
      tokens: null
    })
  },
  {
    context: secureContext,
    name: 'requires a connection mode for CMA',
    valid: false,
    values: cma({
      agentInitiated: false,
      hosts: [],
      pollerInitiated: false,
      port: 4317,
      tokens: null
    })
  },
  {
    context: secureContext,
    name: 'requires a name',
    valid: false,
    values: { ...telegraf({}), name: '' }
  },
  {
    context: secureContext,
    name: 'requires a poller',
    valid: false,
    values: { ...telegraf({}), pollers: [] }
  },
  {
    context: cmaContext({ pollerInitiated: true }),
    name: 'accepts host addresses',
    valid: true,
    values: cma({
      agentInitiated: false,
      hosts: [cmaHost('192.168.1.1')],
      pollerInitiated: true,
      port: null
    })
  }
];

for (const { context, name, valid, values } of cases) {
  test(name, async ({ mount, page }) => {
    await mount('Form/useValidationSchema/Default');

    expect(await validate(page, values, context)).toBe(valid);
  });
}
