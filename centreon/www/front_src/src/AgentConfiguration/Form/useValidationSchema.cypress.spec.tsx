import React from 'react';
import { AgentType } from '../models';
import { useValidationSchema } from './useValidationSchema';

// Dummy component to test the hook
const ValidationSchemaTestComponent: React.FC<{
  onSchemaReady: (schema: any) => void;
}> = ({ onSchemaReady }) => {
  const schema = useValidationSchema();

  React.useEffect(() => {
    onSchemaReady(schema);
  }, [schema, onSchemaReady]);

  return null;
};

describe('useValidationSchema - certificateValidation', () => {
  let schema: any;

  beforeEach(() => {
    // Mock the useTranslation hook for Cypress
    cy.stub().as('useTranslation').returns({
      t: (key: string) => key
    });

    cy.mount(
      {
        Component: <ValidationSchemaTestComponent
          onSchemaReady={(s) => {
            schema = s;
          }}
        />
      }).then(() => {
        // Wait for the component to mount and schema to be set
        cy.wrap(null).should(() => {
          expect(schema).to.exist;
        });
      });
  });

  it('should validate certificate files with .crt extension when connection mode is secure', () => {
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

  it('should validate certificate files with .cer extension when connection mode is secure', () => {
    cy.then(async () => {
      const validData = {
        name: 'test',
        type: { id: AgentType.Telegraf },
        pollers: [{ id: 1, name: 'poller1' }],
        connectionMode: { id: 'secure', name: 'Secure' },
        configuration: {
          confServerPort: 8080,
          otelPublicCertificate: '/path/to/cert.cer'
        }
      };

      const result = await schema.validate(validData, {
        context: { connectionMode: { id: 'secure' } }
      });
      expect(result).to.exist;
    });
  });

  it('should validate key files with .key extension when connection mode is secure', () => {
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

  it('should reject certificate files with invalid extensions', () => {
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

  it('should reject key files with invalid extensions', () => {
    const invalidData = {
      name: 'test',
      type: { id: AgentType.Telegraf },
      pollers: [{ id: 1, name: 'poller1' }],
      connectionMode: { id: 'secure', name: 'Secure' },
      configuration: {
        confServerPort: 8080,
        otelPrivateKey: '/path/to/key.txt'
      }
    };

    cy.then(async () => {
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

  it('should reject paths with double slashes', () => {
    const invalidData = {
      name: 'test',
      type: { id: AgentType.Telegraf },
      pollers: [{ id: 1, name: 'poller1' }],
      connectionMode: { id: 'secure', name: 'Secure' },
      configuration: {
        confServerPort: 8080,
        otelPublicCertificate: '/path//to/cert.crt'
      }
    };

    cy.then(async () => {
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

  it('should reject relative paths starting with ./', () => {
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

    cy.then(async () => {
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

  it('should reject relative paths starting with ../', () => {
    const invalidData = {
      name: 'test',
      type: { id: AgentType.Telegraf },
      pollers: [{ id: 1, name: 'poller1' }],
      connectionMode: { id: 'secure', name: 'Secure' },
      configuration: {
        confServerPort: 8080,
        otelPublicCertificate: '../path/to/cert.crt'
      }
    };

    cy.then(async () => {
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

  it('should allow null values when connection mode is not secure or insecure', () => {
    const validData = {
      name: 'test',
      type: { id: AgentType.Telegraf },
      pollers: [{ id: 1, name: 'poller1' }],
      connectionMode: { id: 'other', name: 'Other' },
      configuration: {
        confServerPort: 8080,
        otelPublicCertificate: null
      }
    };

    cy.then(async () => {
      const result = await schema.validate(validData, {
        context: { connectionMode: { id: 'other' } }
      });
      expect(result).to.exist;
    });
  });

  it('should allow empty values when connection mode is secure', () => {
    const validData = {
      name: 'test',
      type: { id: AgentType.Telegraf },
      pollers: [{ id: 1, name: 'poller1' }],
      connectionMode: { id: 'secure', name: 'Secure' },
      configuration: {
        confServerPort: 8080,
        otelPublicCertificate: ''
      }
    };

    cy.then(async () => {
      const result = await schema.validate(validData, {
        context: { connectionMode: { id: 'secure' } }
      });
      expect(result).to.exist;
    });
  });

  it('should validate when connection mode is insecure', () => {
    const validData = {
      name: 'test',
      type: { id: AgentType.Telegraf },
      pollers: [{ id: 1, name: 'poller1' }],
      connectionMode: { id: 'insecure', name: 'Insecure' },
      configuration: {
        confServerPort: 8080,
        otelPublicCertificate: '/path/to/cert.crt'
      }
    };

    cy.then(async () => {
      const result = await schema.validate(validData, {
        context: { connectionMode: { id: 'insecure' } }
      });
      expect(result).to.exist;
    });
  });
});
