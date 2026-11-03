import type { TFunction } from 'i18next';
import { object } from 'yup';

import { createMacroRow } from '../../../ConfigurationBase/Form/Macros';
import { getPayload, getSchemaShape } from '../../../ResourceForm/blocks';
import {
  labelArgumentsForbiddenTokens,
  labelFillAllArgumentsOrNone,
  labelRequired
} from '../../../ResourceForm/translatedLabels';
import { blocks } from '.';

const t = ((label: string) => label) as unknown as TFunction;

const command = { id: 9, name: 'check-centreon-cpu' };
const template = { id: 30, name: 'generic-active-service' };

// The fields every other block requires, so only the check options fail.
const identity = { host: { id: 10, name: 'host A' }, name: 'cpu' };

const checkOptions = (overrides: object = {}) => ({
  args: '',
  command: null,
  macros: [],
  ...overrides
});

const getErrors = async (
  values: object,
  { isMassChange = false } = {}
): Promise<Array<string>> => {
  const schema = object(
    getSchemaShape(blocks, { isCloudPlatform: false, isMassChange, t })
  );

  try {
    await schema.validate({ ...identity, ...values }, { abortEarly: false });

    return [];
  } catch (error) {
    return (error as { inner: Array<{ path: string; message: string }> }).inner
      .map(({ path, message }) => `${path}: ${message}`)
      .sort();
  }
};

const getCheckOptionsPayload = (values: object): unknown =>
  getPayload(blocks, { ...identity, ...values }, { isCloudPlatform: false })
    .check_options;

// The template field (F03) is MON-213082's: these rules read its `template`
// form key, which no rstest can set yet.
describe('Service check options', () => {
  it('requires a command only without a template', async () => {
    expect(await getErrors({ checkOptions: checkOptions() })).toEqual([
      `checkOptions.command: ${labelRequired}`
    ]);
    expect(await getErrors({ checkOptions: checkOptions(), template })).toEqual(
      []
    );
    expect(
      await getErrors({ checkOptions: checkOptions({ command }) })
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
        checkOptions: checkOptions({
          command,
          macros: [storedPassword, inherited, own]
        })
      })
    ).toEqual([`checkOptions.macros[2].value: ${labelRequired}`]);
  });

  it('sends the arguments with a command of its own or its template', () => {
    expect(
      getCheckOptionsPayload({ checkOptions: checkOptions({ args: '!80' }) })
    ).toEqual({ args: [], command_id: null, macros: [] });
    expect(
      getCheckOptionsPayload({
        checkOptions: checkOptions({ args: '!80' }),
        template
      })
    ).toEqual({ args: ['80'], command_id: null, macros: [] });
    expect(
      getCheckOptionsPayload({
        checkOptions: checkOptions({ args: '80!90', command })
      })
    ).toEqual({ args: ['80', '90'], command_id: 9, macros: [] });
  });
});
