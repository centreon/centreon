import type { InputPropsWithoutGroup } from '@centreon/ui';

import type { TFunction } from 'i18next';
import { JsonDecoder } from 'ts.data.json';
import { number, object, string } from 'yup';

import {
  type AnyFieldBlock,
  getDefaultValues,
  getDetailDecoder,
  getInputBlocks,
  getPayload,
  getSchemaShape
} from './blocks';
import type { PlatformContext, SectionContext } from './models';

const onPremise: PlatformContext = { isCloudPlatform: false };
const cloud: PlatformContext = { isCloudPlatform: true };
const t = ((label: string) => label) as TFunction;
const input = {} as InputPropsWithoutGroup;

// Two blocks filling one object, as the scheduling blocks do.
const period: AnyFieldBlock = {
  defaultValues: { scheduling: { period: null } },
  detailDecoders: {
    scheduling: JsonDecoder.object({ period: JsonDecoder.string }, 'Period')
  },
  getInputs: () => ({ checkPeriodAndIntervals: input }),
  getSchema: () => ({
    scheduling: object({ period: string().required('period required') })
  }),
  toPayload: (values) => ({
    scheduling_options: {
      period: (values.scheduling as { period: string }).period
    }
  })
};

const attempts: AnyFieldBlock = {
  defaultValues: { scheduling: { attempts: null } },
  detailDecoders: {
    scheduling: JsonDecoder.object({ attempts: JsonDecoder.number }, 'Attempts')
  },
  getInputs: () => ({ activeAndPassiveChecks: input }),
  getSchema: () => ({
    scheduling: object({ attempts: number().required('attempts required') })
  }),
  isAvailable: ({ isCloudPlatform }) => !isCloudPlatform,
  toPayload: (values) => ({
    scheduling_options: {
      attempts: (values.scheduling as { attempts: number }).attempts
    }
  })
};

const blocks = [period, attempts];

const values = { scheduling: { attempts: 3, period: '24x7' } };

const decode = (json: unknown, context: PlatformContext = onPremise) => {
  const result = getDetailDecoder({
    blocks,
    context,
    name: 'Resource'
  }).decode(json);

  return result.isOk() ? { value: result.value } : { error: result.error };
};

describe('Field blocks', () => {
  it('merges the defaults of blocks that share a key', () => {
    expect(getDefaultValues(blocks, onPremise)).toEqual({
      scheduling: { attempts: null, period: null }
    });
  });

  it('merges the payloads of blocks that share a key', () => {
    expect(getPayload(blocks, values, onPremise)).toEqual({
      scheduling_options: { attempts: 3, period: '24x7' }
    });
  });

  it('checks the rules of every block on a shared key', () => {
    const schema = object(
      getSchemaShape(blocks, { ...onPremise, t } as SectionContext)
    );

    expect(() => schema.validateSync({ scheduling: {} })).toThrow();
    expect(() =>
      schema.validateSyncAt('scheduling.period', { scheduling: {} })
    ).toThrow('period required');
    expect(() =>
      schema.validateSyncAt('scheduling.attempts', { scheduling: {} })
    ).toThrow('attempts required');
  });

  it('merges the detail every block reads back', () => {
    expect(decode(values)).toEqual({ value: values });
  });

  it('fails the whole detail when one block fails', () => {
    const { error } = decode({
      scheduling: { attempts: 'three', period: '24x7' }
    });

    expect(error).toContain('attempts');
  });

  it('leaves an unavailable block out of everything', () => {
    const context = { ...cloud, t } as SectionContext;

    expect(Object.keys(getInputBlocks(blocks, context))).toEqual([
      'checkPeriodAndIntervals'
    ]);
    expect(getDefaultValues(blocks, cloud)).toEqual({
      scheduling: { period: null }
    });
    expect(getPayload(blocks, values, cloud)).toEqual({
      scheduling_options: { period: '24x7' }
    });
    expect(() =>
      object(getSchemaShape(blocks, context)).validateSyncAt(
        'scheduling.attempts',
        { scheduling: {} }
      )
    ).toThrow();
    expect(decode({ scheduling: { period: '24x7' } }, cloud)).toEqual({
      value: { scheduling: { period: '24x7' } }
    });
  });
});
