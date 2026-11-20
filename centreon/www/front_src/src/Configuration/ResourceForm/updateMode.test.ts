import { getUpdateModeInput } from './updateMode';

const t = ((label: string) => label) as never;

const getModes = (input: ReturnType<typeof getUpdateModeInput>) =>
  input.segmentedButtons?.options.map(({ value }) => value);

describe('getUpdateModeInput', () => {
  it('offers Incremental and Replacement by default, never Removal', () => {
    expect(
      getModes(getUpdateModeInput({ field: 'groups', idPrefix: 'x', t }))
    ).toEqual(['incremental', 'replacement']);
  });

  it('offers the modes a resource asks for, in its order', () => {
    expect(
      getModes(
        getUpdateModeInput({
          field: 'groups',
          idPrefix: 'x',
          modes: ['incremental', 'replacement', 'removal'],
          t
        })
      )
    ).toEqual(['incremental', 'replacement', 'removal']);
  });
});
