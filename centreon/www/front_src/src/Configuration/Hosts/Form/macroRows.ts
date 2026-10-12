import { JsonDecoder } from 'ts.data.json';

export type MacroParent = 'command' | 'template';

type InheritedMacro = Pick<MacroRow, 'isPassword' | 'name' | 'value'>;

export interface MacroRow {
  // A password the server keeps but never sends back: left empty, it is kept.
  hasStoredPassword: boolean;
  // Null for a new macro, and for a command macro the command never recorded.
  id: number | null;
  // An inherited macro as the host received it, null for the host's own.
  inherited: InheritedMacro | null;
  isPassword: boolean;
  name: string;
  // Where an inherited macro comes from, null for the host's own.
  parent: MacroParent | null;
  value: string;
}

export const createMacroRow = (): MacroRow => ({
  hasStoredPassword: false,
  id: null,
  inherited: null,
  isPassword: false,
  name: '',
  parent: null,
  value: ''
});

// As the server stores the name: trimmed, upper-cased.
export const toMacroName = (name: string): string => name.trim().toUpperCase();

interface MacroDetail {
  id: number | null;
  isPassword: boolean;
  name: string;
  parent: MacroParent | null;
  value?: string | null;
}

// `defaultParent` is the origin of a macro sent without one: the host's own on
// a host, the template's own on a template.
const getMacroDecoder = (defaultParent: MacroParent | null) =>
  JsonDecoder.object<MacroDetail>(
    {
      id: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.number)).map(
        (value) => value ?? null
      ),
      isPassword: JsonDecoder.optional(JsonDecoder.boolean).map(
        (value) => value ?? false
      ),
      name: JsonDecoder.string,
      parent: JsonDecoder.optional(
        JsonDecoder.nullable(
          JsonDecoder.oneOf<MacroParent>(
            [
              JsonDecoder.isExactly('template'),
              JsonDecoder.isExactly('command')
            ],
            'Macro parent'
          )
        )
      ).map((value) => value ?? defaultParent),
      // Never sent back for a password.
      value: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string))
    },
    'Macro',
    { isPassword: 'is_password' }
  ).map(({ value, ...macro }): MacroRow => {
    const row = {
      ...macro,
      hasStoredPassword: macro.isPassword,
      value: value ?? ''
    };

    return {
      ...row,
      inherited: row.parent
        ? { isPassword: row.isPassword, name: row.name, value: row.value }
        : null
    };
  });

export const hostMacroDecoder = getMacroDecoder(null);

// `GET /configuration/hosts/host_templates/{id}`: the template's own macros,
// then those it inherits, which all reach a host as inherited.
export const templateMacrosDecoder = JsonDecoder.object(
  {
    macros: JsonDecoder.optional(
      JsonDecoder.array(getMacroDecoder('template'), 'Template macros')
    ).map((value) => value ?? [])
  },
  'Host template'
).map(({ macros }) => macros);

// Still what the host inherits: once changed, it is the host's own override,
// as legacy shows it.
export const isUnchangedInherited = ({
  inherited,
  isPassword,
  name,
  value
}: MacroRow): boolean =>
  !!inherited &&
  inherited.isPassword === isPassword &&
  inherited.name === name &&
  inherited.value === value;

const isSameSource = (macro: MacroRow, other: MacroRow): boolean =>
  macro.parent === other.parent &&
  macro.id === other.id &&
  macro.inherited?.name === other.inherited?.name;

// What a host inherits from its templates' macros, as `InheritedHostMacros`
// resolves it: per name, the first template in order wins (each template's
// macros come nearest first), and command macros only fill names no template
// defines.
//
// Command macros are approximate: the server takes one check command (the
// host's own, or the first one in the template line) plus its templates'
// service template commands, and a template's macros do not say which command
// each comes from. With a host check command, the templates' command macros
// are left out and `hostCommandMacros` (those it was loaded with, while that
// command is unchanged) stand for them; without one, every template's are kept.
export const resolveInheritedMacros = ({
  hasCheckCommand,
  hostCommandMacros = [],
  templatesMacros
}: {
  hasCheckCommand: boolean;
  hostCommandMacros?: Array<MacroRow>;
  templatesMacros: Array<Array<MacroRow>>;
}): Array<MacroRow> => {
  const macros = templatesMacros.flat();
  const candidates = [
    ...macros.filter(({ parent }) => parent === 'template'),
    ...(hasCheckCommand
      ? hostCommandMacros
      : macros.filter(({ parent }) => parent === 'command'))
  ];

  return candidates.filter(
    ({ name }, index) =>
      candidates.findIndex((candidate) => candidate.name === name) === index
  );
};

// The rows once what the host inherits has changed. Rows stay where they are:
// an untouched inherited row no longer inherited goes, a changed one becomes
// the host's own (its source is gone), and newly inherited macros are added at
// the end unless a row already holds their name.
export const mergeInheritedMacros = (
  macros: Array<MacroRow>,
  inherited: Array<MacroRow>
): Array<MacroRow> => {
  const keptMacros = macros.flatMap((macro): Array<MacroRow> => {
    if (
      !macro.parent ||
      inherited.some((inheritedMacro) => isSameSource(macro, inheritedMacro))
    ) {
      return [macro];
    }

    return isUnchangedInherited(macro)
      ? []
      : [
          {
            ...macro,
            hasStoredPassword: false,
            id: null,
            inherited: null,
            parent: null
          }
        ];
  });

  const names = keptMacros.map(({ name }) => toMacroName(name));

  return [
    ...keptMacros,
    ...inherited.filter(
      (inheritedMacro) =>
        !names.includes(toMacroName(inheritedMacro.name)) &&
        !keptMacros.some((macro) => isSameSource(macro, inheritedMacro))
    )
  ];
};
