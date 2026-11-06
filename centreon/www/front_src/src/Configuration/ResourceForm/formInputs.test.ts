import { type InputPropsWithoutGroup, InputType } from '@centreon/ui';

import type { TFunction } from 'i18next';

import { getFormInputs } from './formInputs';
import type { FormValues } from './models';

const t = ((label: string) => label) as TFunction;

const name: InputPropsWithoutGroup = {
  fieldName: 'name',
  label: 'name',
  type: InputType.Text
};

// A field that waits for another one, inside a grid.
const args: InputPropsWithoutGroup = {
  fieldName: 'args',
  getDisabled: (values) => !values.command,
  label: 'args',
  type: InputType.Text
};

const grid: InputPropsWithoutGroup = {
  fieldName: 'check',
  grid: { className: 'grid-cols-1', columns: [args] },
  label: 'check',
  type: InputType.Grid
};

const placedSections = [{ inputs: [name, grid], label: 'Section', order: 1 }];

const isUnlocked = (values: FormValues): boolean => !values.isLocked;

// Whether the plain input and the grid's column are disabled for `values`.
const getDisabledStates = (
  options: Pick<Parameters<typeof getFormInputs>[0], 'canEdit' | 'editable'>,
  values: FormValues
): [boolean, boolean] => {
  const {
    inputs: [plain, block]
  } = getFormInputs({ ...options, placedSections, t });

  return [
    !!plain.getDisabled?.(values),
    !!block.grid?.columns[0].getDisabled?.(values)
  ];
};

describe('getFormInputs', () => {
  it('disables every input without write access only, when nothing refuses the resource', () => {
    expect(getDisabledStates({ canEdit: true }, { command: 'ping' })).toEqual([
      false,
      false
    ]);
    expect(getDisabledStates({ canEdit: false }, { command: 'ping' })).toEqual([
      true,
      true
    ]);
  });

  it('disables a plain input and a grid column of a resource `editable` refuses', () => {
    expect(
      getDisabledStates(
        { canEdit: true, editable: isUnlocked },
        { command: 'ping', isLocked: true }
      )
    ).toEqual([true, true]);
    expect(
      getDisabledStates(
        { canEdit: true, editable: isUnlocked },
        { command: 'ping', isLocked: false }
      )
    ).toEqual([false, false]);
  });

  it("keeps the input's own rule on a resource `editable` accepts", () => {
    expect(
      getDisabledStates(
        { canEdit: true, editable: isUnlocked },
        { command: null, isLocked: false }
      )
    ).toEqual([false, true]);
  });
});
