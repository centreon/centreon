import { type InputProps, InputType } from '@centreon/ui';

import { disableWithoutWriteAccess } from './disableWithoutWriteAccess';

type Input = Omit<InputProps, 'group'>;

const textInput = (fieldName: string): Input => ({
  dataTestId: `service-form-${fieldName}`,
  fieldName,
  label: fieldName,
  type: InputType.Text
});

describe('disableWithoutWriteAccess', () => {
  it('disables every input of a user who may not edit services', () => {
    const input = disableWithoutWriteAccess(false)(textInput('name'));

    expect(input.getDisabled?.({})).toBe(true);
  });

  it('leaves an input editable for a user who may edit services', () => {
    const input = disableWithoutWriteAccess(true)(textInput('name'));

    expect(input.getDisabled?.({})).toBe(false);
  });

  it('keeps the rule the input already had', () => {
    const input = disableWithoutWriteAccess(true)({
      ...textInput('args'),
      getDisabled: (values: Record<string, unknown>) => !values.command
    });

    expect(input.getDisabled?.({})).toBe(true);
    expect(input.getDisabled?.({ command: 'check_ping' })).toBe(false);
  });

  it('disables the columns of a grid too', () => {
    const input = disableWithoutWriteAccess(false)<Input>({
      fieldName: '',
      grid: { columns: [textInput('alias'), textInput('address')] },
      label: 'service-form-grid',
      type: InputType.Grid
    });

    for (const column of input.grid?.columns ?? []) {
      expect(column.getDisabled?.({})).toBe(true);
    }
  });
});
