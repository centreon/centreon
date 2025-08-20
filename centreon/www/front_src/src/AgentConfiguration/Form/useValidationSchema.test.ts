import { renderHook } from '@testing-library/react';
import { AgentType } from '../models';
import { useValidationSchema } from './useValidationSchema';

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => key
  })
}));

describe('useValidationSchema - certificateValidation', () => {
  const getSchema = () => {
    const { result } = renderHook(() => useValidationSchema());
    return result.current;
  };

  it('should validate certificate files with .crt extension when connection mode is secure', async () => {
    const schema = getSchema();
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
    expect(result).toBeDefined();
  });

  it('should validate certificate files with .cer extension when connection mode is secure', async () => {
    const schema = getSchema();
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
    expect(result).toBeDefined();
  });

  it('should validate key files with .key extension when connection mode is secure', async () => {
    const schema = getSchema();
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
    expect(result).toBeDefined();
  });

  it('should reject certificate files with invalid extensions', async () => {
    const schema = getSchema();
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

    await expect(
      schema.validate(invalidData, {
        context: { connectionMode: { id: 'secure' } }
      })
    ).rejects.toThrow();
  });

  it('should reject key files with invalid extensions', async () => {
    const schema = getSchema();
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

    await expect(
      schema.validate(invalidData, {
        context: { connectionMode: { id: 'secure' } }
      })
    ).rejects.toThrow();
  });

  it('should reject paths with double slashes', async () => {
    const schema = getSchema();
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

    await expect(
      schema.validate(invalidData, {
        context: { connectionMode: { id: 'secure' } }
      })
    ).rejects.toThrow();
  });

  it('should reject relative paths starting with ./', async () => {
    const schema = getSchema();
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

    await expect(
      schema.validate(invalidData, {
        context: { connectionMode: { id: 'secure' } }
      })
    ).rejects.toThrow();
  });

  it('should reject relative paths starting with ../', async () => {
    const schema = getSchema();
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

    await expect(
      schema.validate(invalidData, {
        context: { connectionMode: { id: 'secure' } }
      })
    ).rejects.toThrow();
  });

  it('should allow null values when connection mode is not secure or insecure', async () => {
    const schema = getSchema();
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

    const result = await schema.validate(validData, {
      context: { connectionMode: { id: 'other' } }
    });
    expect(result).toBeDefined();
  });

  it('should allow empty values when connection mode is secure', async () => {
    const schema = getSchema();
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

    const result = await schema.validate(validData, {
      context: { connectionMode: { id: 'secure' } }
    });
    expect(result).toBeDefined();
  });

  it('should validate when connection mode is insecure', async () => {
    const schema = getSchema();
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

    const result = await schema.validate(validData, {
      context: { connectionMode: { id: 'insecure' } }
    });
    expect(result).toBeDefined();
  });
});
