import React from 'react';
import { AnyObject, ObjectSchema } from 'yup';

import { AgentType } from '../models';
import { useValidationSchema } from './useValidationSchema';

const ValidationSchemaTestComponent: React.FC<{
  onSchemaReady: (schema) => void;
}> = ({ onSchemaReady }) => {
  const schema = useValidationSchema();

  React.useEffect(() => {
    onSchemaReady(schema);
  }, [schema, onSchemaReady]);

  return null;
};

describe('useValidationSchema', () => {
  let schema: ObjectSchema<{}, AnyObject, {}, ''>;

  beforeEach(() => {
    cy.stub()
      .as('useTranslation')
      .returns({
        t: (key: string) => key
      });

    cy.mount({
      Component: (
        <ValidationSchemaTestComponent
          onSchemaReady={(s) => {
            schema = s;
          }}
        />
      )
    }).then(() => {
      cy.wrap(null).should(() => {
        expect(schema).to.exist;
      });
    });
  });

  it('validate certificate files with .crt extension', () => {
    cy.then(async () => {
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

      const result = await schema.validate(validData, {
        context: { connectionMode: { id: 'secure' } }
      });
      expect(result).to.exist;
    });
  });

  it('validate key files with .key extension', () => {
    cy.then(async () => {
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

      const result = await schema.validate(validData, {
        context: { connectionMode: { id: 'secure' } }
      });
      expect(result).to.exist;
    });
  });

  it('reject invalid certificate extensions', () => {
    cy.then(async () => {
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

      try {
        await schema.validate(invalidData, {
          context: { connectionMode: { id: 'secure' } }
        });
        throw new Error('Expected validation to fail');
      } catch (error) {
        expect(error).to.exist;
      }
    });
  });

  it('reject relative paths', () => {
    cy.then(async () => {
      const invalidData = {
        configuration: {
          confServerPort: 8080,
          otelPublicCertificate: './path/to/cert.crt'
        },
        connectionMode: { id: 'secure', name: 'Secure' },
        name: 'test',
        pollers: [{ id: 1, name: 'poller1' }],
        type: { id: AgentType.Telegraf }
      };

      try {
        await schema.validate(invalidData, {
          context: { connectionMode: { id: 'secure' } }
        });
        throw new Error('Expected validation to fail');
      } catch (error) {
        expect(error).to.exist;
      }
    });
  });

  it('validate valid port numbers', () => {
    cy.then(async () => {
      const validData = {
        configuration: {
          confServerPort: 443
        },
        connectionMode: { id: 'secure', name: 'Secure' },
        name: 'test',
        pollers: [{ id: 1, name: 'poller1' }],
        type: { id: AgentType.Telegraf }
      };

      const result = await schema.validate(validData, {
        context: { connectionMode: { id: 'secure' } }
      });
      expect(result).to.exist;
    });
  });

  it('reject invalid port numbers', () => {
    cy.then(async () => {
      const invalidData = {
        configuration: {
          confServerPort: 0
        },
        connectionMode: { id: 'secure', name: 'Secure' },
        name: 'test',
        pollers: [{ id: 1, name: 'poller1' }],
        type: { id: AgentType.Telegraf }
      };

      try {
        await schema.validate(invalidData, {
          context: { connectionMode: { id: 'secure' } }
        });
        throw new Error('Expected validation to fail');
      } catch (error) {
        expect(error).to.exist;
      }
    });
  });

  it('validate CMA agent with agentInitiated', () => {
    cy.then(async () => {
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

      const result = await schema.validate(validData, {
        context: {
          configuration: { agentInitiated: true },
          connectionMode: { id: 'secure' },
          type: { id: AgentType.CMA }
        }
      });
      expect(result).to.exist;
    });
  });

  it('validate CMA agent with pollerInitiated', () => {
    cy.then(async () => {
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

      const result = await schema.validate(validData, {
        context: {
          configuration: { pollerInitiated: true },
          connectionMode: { id: 'secure' },
          type: { id: AgentType.CMA }
        }
      });
      expect(result).to.exist;
    });
  });

  it('require at least one connection mode for CMA', () => {
    cy.then(async () => {
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

      try {
        await schema.validate(invalidData, {
          context: { connectionMode: { id: 'secure' } }
        });
        throw new Error('Expected validation to fail');
      } catch (error) {
        expect(error).to.exist;
      }
    });
  });

  it('require agent name', () => {
    cy.then(async () => {
      const invalidData = {
        configuration: {
          confServerPort: 8080
        },
        connectionMode: { id: 'secure', name: 'Secure' },
        name: '',
        pollers: [{ id: 1, name: 'poller1' }],
        type: { id: AgentType.Telegraf }
      };

      try {
        await schema.validate(invalidData, {
          context: { connectionMode: { id: 'secure' } }
        });
        throw new Error('Expected validation to fail');
      } catch (error) {
        expect(error).to.exist;
      }
    });
  });

  it('require at least one poller', () => {
    cy.then(async () => {
      const invalidData = {
        configuration: {
          confServerPort: 8080
        },
        connectionMode: { id: 'secure', name: 'Secure' },
        name: 'test',
        pollers: [],
        type: { id: AgentType.Telegraf }
      };

      try {
        await schema.validate(invalidData, {
          context: { connectionMode: { id: 'secure' } }
        });
        throw new Error('Expected validation to fail');
      } catch (error) {
        expect(error).to.exist;
      }
    });
  });

  it('validate host addresses', () => {
    cy.then(async () => {
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

      const result = await schema.validate(validData, {
        context: {
          configuration: { pollerInitiated: true },
          configuration: {
            pollerInitiated: true
          },
          connectionMode: { id: 'secure' },
          type: { id: AgentType.CMA }
        }
      });
      expect(result).to.exist;
    });
  });
});
