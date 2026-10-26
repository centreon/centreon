import type { TFunction } from 'i18next';
import { object } from 'yup';

import { createMacroRow } from '../../../ConfigurationBase/Form/Macros';
import {
  labelArgumentsForbiddenTokens,
  labelFillAllArgumentsOrNone
} from '../../translatedLabels';
import {
  checkOptions as checkOptionsSection,
  getCheckOptionsSchema,
  toCheckOptionsPayload
} from './checkOptions';

const t = ((label: string) => label) as unknown as TFunction;

const checkCommand = {
  id: 9,
  isCentreonMonitoringAgent: false,
  name: 'check-centreon-cpu'
};
const template = { id: 30, name: 'generic-active-service' };

const getErrors = async (
  values: object,
  { isMassChange = false } = {}
): Promise<Array<string>> => {
  const schema = object(getCheckOptionsSchema({ isMassChange, t }));

  try {
    await schema.validate(values, { abortEarly: false });

    return [];
  } catch (error) {
    return (error as { inner: Array<{ path: string; message: string }> }).inner
      .map(({ path, message }) => `${path}: ${message}`)
      .sort();
  }
};

const checkOptions = (overrides: object = {}) => ({
  args: '',
  macros: [],
  ...overrides
});

// The template field (F03) is another sub-task's: these rules read it from the
// form values, where no rstest can set it yet.
describe('Service check options', () => {
  it('requires a command only without a template', async () => {
    expect(await getErrors({ checkOptions: checkOptions() })).toEqual([
      'checkCommand: Required'
    ]);
    expect(await getErrors({ checkOptions: checkOptions(), template })).toEqual(
      []
    );
    expect(
      await getErrors({ checkCommand, checkOptions: checkOptions() })
    ).toEqual([]);
  });

  it('requires nothing in a mass change', async () => {
    expect(
      await getErrors({ checkOptions: checkOptions() }, { isMassChange: true })
    ).toEqual([]);
  });

  it('validates arguments with an inherited command', async () => {
    expect(
      await getErrors({
        checkOptions: checkOptions({ args: '!80!!90' }),
        template
      })
    ).toEqual([`checkOptions.args: ${labelFillAllArgumentsOrNone}`]);
    expect(
      await getErrors({
        checkOptions: checkOptions({ args: '!80#T#' }),
        template
      })
    ).toEqual([`checkOptions.args: ${labelArgumentsForbiddenTokens}`]);
    expect(
      await getErrors({ checkOptions: checkOptions({ args: '!!' }), template })
    ).toEqual([]);
  });

  it('requires a macro value, but for a stored password or an untouched inherited macro', async () => {
    const own = { ...createMacroRow(), name: 'PORT' };
    const storedPassword = {
      ...createMacroRow(),
      hasStoredPassword: true,
      id: 2,
      isPassword: true,
      name: 'TOKEN'
    };
    const inherited = {
      ...createMacroRow(),
      id: 4,
      inherited: { isPassword: false, name: 'WARNING', value: '' },
      name: 'WARNING',
      parent: 'command' as const
    };

    expect(
      await getErrors({
        checkCommand,
        checkOptions: checkOptions({
          macros: [storedPassword, inherited, own]
        })
      })
    ).toEqual(['checkOptions.macros[2].value: Required']);
  });

  it('sends the arguments with a command of its own or its template', () => {
    expect(
      toCheckOptionsPayload({ checkOptions: checkOptions({ args: '!80' }) })
    ).toEqual({ check_options: { args: [], command_id: null, macros: [] } });
    expect(
      toCheckOptionsPayload({
        checkOptions: checkOptions({ args: '!80' }),
        template
      })
    ).toEqual({
      check_options: { args: ['80'], command_id: null, macros: [] }
    });
    expect(
      toCheckOptionsPayload({
        checkCommand,
        checkOptions: checkOptions({ args: '80!90' })
      })
    ).toEqual({
      check_options: { args: ['80', '90'], command_id: 9, macros: [] }
    });
  });

  // Data Processing (MON-213085) reads it to force the freshness check.
  it('tells a Centreon Monitoring Agent command from the detail', async () => {
    const decoder = checkOptionsSection.detailDecoders.checkCommand;

    expect(
      await decoder.decodeToPromise({
        command: {
          id: 12,
          is_centreon_monitoring_agent: true,
          name: 'cma-linux-cpu'
        }
      })
    ).toEqual({
      id: 12,
      isCentreonMonitoringAgent: true,
      name: 'cma-linux-cpu'
    });
    expect(
      await decoder.decodeToPromise({ command: { id: 9, name: 'check-cpu' } })
    ).toEqual({ id: 9, isCentreonMonitoringAgent: false, name: 'check-cpu' });
    expect(await decoder.decodeToPromise(undefined)).toBeNull();
  });
});
