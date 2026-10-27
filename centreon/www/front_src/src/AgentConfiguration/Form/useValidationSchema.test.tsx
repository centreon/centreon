import { renderHook } from '@testing-library/react';
import { ObjectSchema, ValidationError } from 'yup';

import { AgentType } from '../models';
import {
  labelAtLeastOneConnexionMode,
  labelInvalidExtension,
  labelPortMustStartFrom1,
  labelRelativePathAreNotAllowed,
  labelRequired
} from '../translatedLabels';
import { useValidationSchema } from './useValidationSchema';

// Matches a ValidationError (collected with abortEarly: false) that reports
// the given message on the given field path.
const validationErrorOn = ({
  path,
  message
}: {
  path: string;
  message: string;
}): ValidationError =>
  expect.objectContaining({
    inner: expect.arrayContaining([expect.objectContaining({ message, path })]),
    name: 'ValidationError'
  });

describe('useValidationSchema', () => {
  let schema: ObjectSchema<object>;

  beforeEach(() => {
    const { result } = renderHook(() => useValidationSchema());

    schema = result.current;

    expect(schema).toBeDefined();
  });

  it('validate certificate files with .crt extension', async () => {
    const validData = {
      configuration: {
        confServerPort: 8080,
        otelPublicCertificate: '/path/to/cert.crt'
      },
      connectionMode: { id: 'secure', name: 'Secure' },
      name: 'test',
      pollers: [{ id: 1, name: 'poller1' }],
      type: { id: AgentType.Telegraf }
    };

    // A Telegraf configuration with a .crt public certificate is accepted
    await expect(
      schema.validate(validData, {
        context: { connectionMode: { id: 'secure' } }
      })
    ).resolves.toEqual(validData);
  });

  it('validate certificate files with .cert extension', async () => {
    const validData = {
      configuration: {
        confServerPort: 8080,
        otelPublicCertificate: 'cert.cert'
      },
      connectionMode: { id: 'secure', name: 'Secure' },
      name: 'test',
      pollers: [{ id: 1, name: 'poller1' }],
      type: { id: AgentType.Telegraf }
    };

    // A .cert public certificate is accepted
    await expect(
      schema.validate(validData, {
        context: { connectionMode: { id: 'secure' } }
      })
    ).resolves.toEqual(validData);
  });

  it('validate key files with .key extension', async () => {
    const validData = {
      configuration: {
        confServerPort: 8080,
        otelPrivateKey: '/path/to/key.key'
      },
      connectionMode: { id: 'secure', name: 'Secure' },
      name: 'test',
      pollers: [{ id: 1, name: 'poller1' }],
      type: { id: AgentType.Telegraf }
    };

    // A .key private key is accepted
    await expect(
      schema.validate(validData, {
        context: { connectionMode: { id: 'secure' } }
      })
    ).resolves.toEqual(validData);
  });

  it('reject invalid certificate extensions', async () => {
    const invalidData = {
      configuration: {
        confServerPort: 8080,
        otelPublicCertificate: '/path/to/cert.txt'
      },
      connectionMode: { id: 'secure', name: 'Secure' },
      name: 'test',
      pollers: [{ id: 1, name: 'poller1' }],
      type: { id: AgentType.Telegraf }
    };

    // A .txt public certificate is rejected with an invalid extension error
    await expect(
      schema.validate(invalidData, {
        abortEarly: false,
        context: { connectionMode: { id: 'secure' } }
      })
    ).rejects.toEqual(
      validationErrorOn({
        message: labelInvalidExtension,
        path: 'configuration.otelPublicCertificate'
      })
    );
  });

  it('reject relative paths', async () => {
    const invalidData = {
      configuration: {
        confServerPort: 8080,
        otelPublicCertificate: './path/to/cert.cert'
      }
    };

    // A certificate path starting with ./ is rejected as a relative path
    await expect(
      schema.validate(invalidData, {
        abortEarly: false,
        context: { connectionMode: { id: 'secure' } }
      })
    ).rejects.toEqual(
      validationErrorOn({
        message: labelRelativePathAreNotAllowed,
        path: 'configuration.otelPublicCertificate'
      })
    );
  });

  it('validate valid port numbers', async () => {
    const validData = {
      configuration: {
        confServerPort: 443
      },
      connectionMode: { id: 'secure', name: 'Secure' },
      name: 'test',
      pollers: [{ id: 1, name: 'poller1' }],
      type: { id: AgentType.Telegraf }
    };

    // Port 443 is within the 1-65535 range and is accepted
    await expect(
      schema.validate(validData, {
        context: { connectionMode: { id: 'secure' } }
      })
    ).resolves.toEqual(validData);
  });

  it('reject invalid port numbers', async () => {
    const invalidData = {
      configuration: {
        confServerPort: 0
      },
      connectionMode: { id: 'secure', name: 'Secure' },
      name: 'test',
      pollers: [{ id: 1, name: 'poller1' }],
      type: { id: AgentType.Telegraf }
    };

    // Port 0 is below the lower bound and is rejected
    await expect(
      schema.validate(invalidData, {
        abortEarly: false,
        context: { connectionMode: { id: 'secure' } }
      })
    ).rejects.toEqual(
      validationErrorOn({
        message: labelPortMustStartFrom1,
        path: 'configuration.confServerPort'
      })
    );
  });

  it('validate CMA agent with agentInitiated', async () => {
    const validData = {
      configuration: {
        agentInitiated: true,
        hosts: [],
        pollerInitiated: false,
        port: 4317,
        tokens: [{ creatorId: 1, id: '1', name: 'token1' }]
      },
      connectionMode: { id: 'secure', name: 'Secure' },
      name: 'test',
      pollers: [{ id: 1, name: 'poller1' }],
      type: { id: AgentType.CMA }
    };

    // An agent-initiated CMA with a port and a token is accepted
    await expect(
      schema.validate(validData, {
        context: {
          configuration: { agentInitiated: true },
          connectionMode: { id: 'secure' },
          type: { id: AgentType.CMA }
        }
      })
    ).resolves.toEqual(validData);
  });

  it('validate CMA agent with pollerInitiated', async () => {
    const validData = {
      configuration: {
        agentInitiated: false,
        hosts: [
          {
            address: '192.168.1.100',
            pollerCaCertificate: null,
            pollerCaName: null,
            port: 4317,
            token: {
              creatorId: 1,
              id: '1',
              name: 'token1',
              token_name: 'test-token'
            }
          }
        ],
        pollerInitiated: true,
        port: null,
        tokens: null
      },
      connectionMode: { id: 'secure', name: 'Secure' },
      name: 'test',
      pollers: [{ id: 1, name: 'poller1' }],
      type: { id: AgentType.CMA }
    };

    // A poller-initiated CMA with one host and its token is accepted
    await expect(
      schema.validate(validData, {
        context: {
          configuration: { pollerInitiated: true },
          connectionMode: { id: 'secure' },
          type: { id: AgentType.CMA }
        }
      })
    ).resolves.toEqual(validData);
  });

  it('require at least one connection mode for CMA', async () => {
    const invalidData = {
      configuration: {
        agentInitiated: false,
        hosts: [],
        pollerInitiated: false,
        port: 4317,
        tokens: null
      },
      connectionMode: { id: 'secure', name: 'Secure' },
      name: 'test',
      pollers: [{ id: 1, name: 'poller1' }],
      type: { id: AgentType.CMA }
    };

    // A CMA with neither agent- nor poller-initiated connection is rejected
    await expect(
      schema.validate(invalidData, {
        abortEarly: false,
        context: { connectionMode: { id: 'secure' } }
      })
    ).rejects.toEqual(
      validationErrorOn({
        message: labelAtLeastOneConnexionMode,
        path: 'configuration'
      })
    );
  });

  it('require agent name', async () => {
    const invalidData = {
      configuration: {
        confServerPort: 8080
      },
      connectionMode: { id: 'secure', name: 'Secure' },
      name: '',
      pollers: [{ id: 1, name: 'poller1' }],
      type: { id: AgentType.Telegraf }
    };

    // An empty name is rejected as required
    await expect(
      schema.validate(invalidData, {
        abortEarly: false,
        context: { connectionMode: { id: 'secure' } }
      })
    ).rejects.toEqual(
      validationErrorOn({ message: labelRequired, path: 'name' })
    );
  });

  it('require at least one poller', async () => {
    const invalidData = {
      configuration: {
        confServerPort: 8080
      },
      connectionMode: { id: 'secure', name: 'Secure' },
      name: 'test',
      pollers: [],
      type: { id: AgentType.Telegraf }
    };

    // An empty poller list is rejected as required
    await expect(
      schema.validate(invalidData, {
        abortEarly: false,
        context: { connectionMode: { id: 'secure' } }
      })
    ).rejects.toEqual(
      validationErrorOn({ message: labelRequired, path: 'pollers' })
    );
  });

  it('validate host addresses', async () => {
    const validData = {
      configuration: {
        agentInitiated: false,
        hosts: [
          {
            address: '192.168.1.1',
            pollerCaCertificate: null,
            pollerCaName: null,
            port: 4317,
            token: {
              creatorId: 1,
              id: '1',
              name: 'token1',
              token_name: 'test-token'
            }
          }
        ],
        pollerInitiated: true,
        port: null
      },
      connectionMode: { id: 'secure', name: 'Secure' },
      name: 'test',
      pollers: [{ id: 1, name: 'poller1' }],
      type: { id: AgentType.CMA }
    };

    // A poller-initiated CMA host with an IPv4 address is accepted
    await expect(
      schema.validate(validData, {
        context: {
          configuration: {
            pollerInitiated: true
          },
          connectionMode: { id: 'secure' },
          type: { id: AgentType.CMA }
        }
      })
    ).resolves.toEqual(validData);
  });
});
