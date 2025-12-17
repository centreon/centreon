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
        name: 'test',
        type: { id: AgentType.Telegraf },
        pollers: [{ id: 1, name: 'poller1' }],
        connectionMode: { id: 'secure', name: 'Secure' },
        configuration: {
          confServerPort: 8080,
          otelPublicCertificate: '/path/to/cert.crt'
        }
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
        name: 'test',
        type: { id: AgentType.Telegraf },
        pollers: [{ id: 1, name: 'poller1' }],
        connectionMode: { id: 'secure', name: 'Secure' },
        configuration: {
          confServerPort: 8080,
          otelPrivateKey: '/path/to/key.key'
        }
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
        name: 'test',
        type: { id: AgentType.Telegraf },
        pollers: [{ id: 1, name: 'poller1' }],
        connectionMode: { id: 'secure', name: 'Secure' },
        configuration: {
          confServerPort: 8080,
          otelPublicCertificate: '/path/to/cert.txt'
        }
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
        name: 'test',
        type: { id: AgentType.Telegraf },
        pollers: [{ id: 1, name: 'poller1' }],
        connectionMode: { id: 'secure', name: 'Secure' },
        configuration: {
          confServerPort: 8080,
          otelPublicCertificate: './path/to/cert.crt'
        }
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
        name: 'test',
        type: { id: AgentType.Telegraf },
        pollers: [{ id: 1, name: 'poller1' }],
        connectionMode: { id: 'secure', name: 'Secure' },
        configuration: {
          confServerPort: 443
        }
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
        name: 'test',
        type: { id: AgentType.Telegraf },
        pollers: [{ id: 1, name: 'poller1' }],
        connectionMode: { id: 'secure', name: 'Secure' },
        configuration: {
          confServerPort: 0
        }
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
        name: 'test',
        type: { id: AgentType.CMA },
        pollers: [{ id: 1, name: 'poller1' }],
        connectionMode: { id: 'secure', name: 'Secure' },
        configuration: {
          agentInitiated: true,
          pollerInitiated: false,
          tokens: [{ id: '1', name: 'token1', creatorId: 1 }],
          hosts: []
        }
      };

      const result = await schema.validate(validData, {
        context: {
          connectionMode: { id: 'secure' },
          type: { id: AgentType.CMA },
          configuration: { agentInitiated: true }
        }
      });
      expect(result).to.exist;
    });
  });

  it('validate CMA agent with pollerInitiated', () => {
    cy.then(async () => {
      const validData = {
        name: 'test',
        type: { id: AgentType.CMA },
        pollers: [{ id: 1, name: 'poller1' }],
        connectionMode: { id: 'secure', name: 'Secure' },
        configuration: {
          agentInitiated: false,
          pollerInitiated: true,
          tokens: null,
          hosts: [
            {
              address: '192.168.1.100',
              port: 4317,
              pollerCaCertificate: null,
              pollerCaName: null,
              token: {
                id: '1',
                name: 'token1',
                creatorId: 1,
                token_name: 'test-token'
              }
            }
          ]
        }
      };

      const result = await schema.validate(validData, {
        context: {
          connectionMode: { id: 'secure' },
          type: { id: AgentType.CMA },
          configuration: { pollerInitiated: true }
        }
      });
      expect(result).to.exist;
    });
  });

  it('require at least one connection mode for CMA', () => {
    cy.then(async () => {
      const invalidData = {
        name: 'test',
        type: { id: AgentType.CMA },
        pollers: [{ id: 1, name: 'poller1' }],
        connectionMode: { id: 'secure', name: 'Secure' },
        configuration: {
          agentInitiated: false,
          pollerInitiated: false,
          tokens: null,
          hosts: []
        }
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
        name: '',
        type: { id: AgentType.Telegraf },
        pollers: [{ id: 1, name: 'poller1' }],
        connectionMode: { id: 'secure', name: 'Secure' },
        configuration: {
          confServerPort: 8080
        }
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
        name: 'test',
        type: { id: AgentType.Telegraf },
        pollers: [],
        connectionMode: { id: 'secure', name: 'Secure' },
        configuration: {
          confServerPort: 8080
        }
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
        name: 'test',
        type: { id: AgentType.CMA },
        pollers: [{ id: 1, name: 'poller1' }],
        connectionMode: { id: 'secure', name: 'Secure' },
        configuration: {
          agentInitiated: false,
          pollerInitiated: true,
          hosts: [
            {
              address: '192.168.1.1',
              port: 4317,
              pollerCaCertificate: null,
              pollerCaName: null,
              token: {
                id: '1',
                name: 'token1',
                creatorId: 1,
                token_name: 'test-token'
              }
            }
          ]
        }
      };

      const result = await schema.validate(validData, {
        context: {
          connectionMode: { id: 'secure' },
          type: { id: AgentType.CMA },
          configuration: { pollerInitiated: true }
        }
      });
      expect(result).to.exist;
    });
  });
});
