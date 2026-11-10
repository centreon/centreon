import type { TFunction } from 'i18next';

import { getInputBlocks, getPayload } from '../blocks';
import type { FormValues, ResourceOptions } from '../models';
import { getCheckCommandBlock } from './checkCommand';

const t = ((label: string) => label) as TFunction;
const context = { isCloudPlatform: false };
const resource: ResourceOptions = { baseEndpoint: './api', idPrefix: 'form' };

const withoutCommand: FormValues = {
  checkOptions: { args: '!80!90', command: null }
};
const withCommand: FormValues = {
  checkOptions: { args: '!80!90', command: { id: 9, name: 'ping' } }
};

const getBlockOf = (
  options: Partial<Parameters<typeof getCheckCommandBlock>[0]> = {}
) =>
  getCheckCommandBlock({
    endpoint: '/commands',
    resource,
    title: 'Check options',
    ...options
  });

const isArgumentsDisabled = (
  block: ReturnType<typeof getBlockOf>,
  values: FormValues
): boolean => {
  const { checkCommand } = getInputBlocks([block], { ...context, t });

  return !!checkCommand?.grid?.columns[1].getDisabled?.(values);
};

describe('getCheckCommandBlock', () => {
  it('takes arguments only with a command, by default', () => {
    const block = getBlockOf();

    expect(isArgumentsDisabled(block, withoutCommand)).toBe(true);
    expect(isArgumentsDisabled(block, withCommand)).toBe(false);
    expect(getPayload([block], withoutCommand, context)).toEqual({
      check_options: { args: [], command_id: null }
    });
    expect(getPayload([block], withCommand, context)).toEqual({
      check_options: { args: ['80', '90'], command_id: 9 }
    });
  });

  it('takes arguments without a command of its own when the resource inherits one', () => {
    const block = getBlockOf({ hasInheritedCommand: () => true });

    expect(isArgumentsDisabled(block, withoutCommand)).toBe(false);
    expect(getPayload([block], withoutCommand, context)).toEqual({
      check_options: { args: ['80', '90'], command_id: null }
    });
  });
});
