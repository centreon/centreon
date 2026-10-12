import {
  type MacroParent,
  type MacroRow,
  mergeInheritedMacros,
  resolveInheritedMacros
} from './macroRows';

const own = (name: string, value = ''): MacroRow => ({
  hasStoredPassword: false,
  id: null,
  inherited: null,
  isErased: false,
  isPassword: false,
  name,
  parent: null,
  value
});

const inheritedFrom =
  (parent: MacroParent) =>
  (id: number | null, name: string, value = ''): MacroRow => ({
    hasStoredPassword: false,
    id,
    inherited: { isPassword: false, name, value },
    isErased: false,
    isPassword: false,
    name,
    parent,
    value
  });

const fromTemplate = inheritedFrom('template');
const fromCommand = inheritedFrom('command');

describe('resolveInheritedMacros', () => {
  it('lets the first template defining a name win', () => {
    expect(
      resolveInheritedMacros({
        hasCheckCommand: false,
        templatesMacros: [
          [fromTemplate(21, 'OS', 'linux')],
          [fromTemplate(51, 'OS', 'windows'), fromTemplate(52, 'PORT', '80')]
        ]
      })
    ).toEqual([
      fromTemplate(21, 'OS', 'linux'),
      fromTemplate(52, 'PORT', '80')
    ]);
  });

  it('lets any template win over a command, whatever their order', () => {
    expect(
      resolveInheritedMacros({
        hasCheckCommand: false,
        templatesMacros: [
          [fromCommand(41, 'WARNING')],
          [fromTemplate(52, 'WARNING', '80')]
        ]
      })
    ).toEqual([fromTemplate(52, 'WARNING', '80')]);
  });

  it('keeps the command macros the host was loaded with, below any template', () => {
    expect(
      resolveInheritedMacros({
        hasCheckCommand: true,
        hostCommandMacros: [fromCommand(4, 'WARNING'), fromCommand(5, 'OS')],
        templatesMacros: [[fromTemplate(21, 'OS'), fromCommand(41, 'CRITICAL')]]
      })
    ).toEqual([fromTemplate(21, 'OS'), fromCommand(4, 'WARNING')]);
  });

  it("leaves the templates' command macros out once the host has its own command", () => {
    expect(
      resolveInheritedMacros({
        hasCheckCommand: true,
        templatesMacros: [[fromTemplate(21, 'OS'), fromCommand(41, 'WARNING')]]
      })
    ).toEqual([fromTemplate(21, 'OS')]);
  });
});

describe('mergeInheritedMacros', () => {
  it('adds what is newly inherited after the rows', () => {
    expect(
      mergeInheritedMacros([own('PORT', '161')], [fromTemplate(21, 'OS')])
    ).toEqual([own('PORT', '161'), fromTemplate(21, 'OS')]);
  });

  it('drops an untouched row no longer inherited', () => {
    expect(
      mergeInheritedMacros(
        [fromTemplate(21, 'OS'), own('PORT')],
        [fromTemplate(31, 'TIMEOUT')]
      )
    ).toEqual([own('PORT'), fromTemplate(31, 'TIMEOUT')]);
  });

  it('keeps a changed row, as the host own once its source is gone', () => {
    const overridden = { ...fromTemplate(21, 'OS', 'linux'), value: 'debian' };

    expect(mergeInheritedMacros([overridden], [])).toEqual([
      own('OS', 'debian')
    ]);
    // Still inherited: it stays an override.
    expect(
      mergeInheritedMacros([overridden], [fromTemplate(21, 'OS', 'linux')])
    ).toEqual([overridden]);
  });

  it('does not add a name a row already holds', () => {
    expect(
      mergeInheritedMacros([own('os', 'mine')], [fromTemplate(21, 'OS')])
    ).toEqual([own('os', 'mine')]);
  });

  it('leaves the rows in place', () => {
    const macros = [own('A'), fromTemplate(21, 'OS'), own('B')];

    expect(mergeInheritedMacros(macros, [fromTemplate(21, 'OS')])).toEqual(
      macros
    );
  });
});
