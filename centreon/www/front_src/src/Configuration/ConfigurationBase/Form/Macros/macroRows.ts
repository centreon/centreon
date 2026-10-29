import { JsonDecoder } from 'ts.data.json';

export type MacroParent = 'command' | 'template';

type InheritedMacro = Pick<MacroRow, 'isPassword' | 'name' | 'value'>;

// One custom macro of a resource (a host, a service), its own or inherited.
export interface MacroRow {
  // A password the server keeps but never sends back: left empty, it is kept.
  hasStoredPassword: boolean;
  // Null for a new macro, and for a command macro the command never recorded.
  id: number | null;
  // An inherited macro as the resource received it, null for its own.
  inherited: InheritedMacro | null;
  // A stored password the user cleared to type a new one.
  isErased: boolean;
  isPassword: boolean;
  name: string;
  // Where an inherited macro comes from, null for the resource's own.
  parent: MacroParent | null;
  value: string;
}

export const createMacroRow = (): MacroRow => ({
  hasStoredPassword: false,
  id: null,
  inherited: null,
  isErased: false,
  isPassword: false,
  name: '',
  parent: null,
  value: ''
});

// As the server stores the name: trimmed, upper-cased.
export const toMacroName = (name: string): string => name.trim().toUpperCase();

// The name as the server stores it, `$_<RESOURCETYPE>PORT$` for `port`.
export const toStoredMacroName = ({
  name,
  prefix
}: {
  name: string;
  prefix: string;
}): string => `${prefix}${toMacroName(name)}$`;

interface MacroDetail {
  id: number | null;
  isPassword: boolean;
  name: string;
  parent: MacroParent | null;
  value?: string | null;
}

// `defaultParent` is the origin of a macro sent without one: the resource's
// own on a host or a service, the template's own on a template.
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
      isErased: false,
      value: value ?? ''
    };

    return {
      ...row,
      inherited: row.parent
        ? { isPassword: row.isPassword, name: row.name, value: row.value }
        : null
    };
  });

// The macros in a resource's detail, `check_options.macros` on a host.
export const macroDecoder = getMacroDecoder(null);

// A template's detail, as `GET /configuration/hosts/host_templates/{id}`: the
// template's own macros, then those it inherits, which all reach the resource
// as inherited.
export const templateMacrosDecoder = JsonDecoder.object(
  {
    macros: JsonDecoder.optional(
      JsonDecoder.array(getMacroDecoder('template'), 'Template macros')
    ).map((value) => value ?? [])
  },
  'Template'
).map(({ macros }) => macros);

// Still what the resource inherits: once changed, it is the resource's own
// override, as legacy shows it.
export const isUnchangedInherited = ({
  inherited,
  isErased,
  isPassword,
  name,
  value
}: MacroRow): boolean =>
  !!inherited &&
  !isErased &&
  inherited.isPassword === isPassword &&
  inherited.name === name &&
  inherited.value === value;

// A changed inherited macro goes back with its id and parent, so the server
// turns it into the resource's own.
export const toMacroPayload = ({
  hasStoredPassword,
  id,
  isErased,
  isPassword,
  name,
  parent,
  value
}: MacroRow) => ({
  id,
  is_password: isPassword,
  name: toMacroName(name),
  parent,
  // Null keeps a stored password; once cleared, what is typed replaces it,
  // empty included.
  value: hasStoredPassword && !isErased ? null : value
});

// What is sent: unchanged inherited macros stay inherited. Not sent, they
// cannot go stale once the templates or the command change.
export const toMacrosPayload = (macros: Array<MacroRow> = []) =>
  macros.filter((macro) => !isUnchangedInherited(macro)).map(toMacroPayload);

const isSameSource = (macro: MacroRow, other: MacroRow): boolean =>
  macro.parent === other.parent &&
  macro.id === other.id &&
  macro.inherited?.name === other.inherited?.name;

// What a resource inherits from its templates' macros, as the hosts'
// `InheritedHostMacros` resolves it: per name, the first template in order
// wins (each template's macros come nearest first), and command macros only
// fill names no template defines.
//
// Command macros are approximate: the server takes one check command (the
// resource's own, or the first one in the template line) plus its templates'
// service template commands, and a template's macros do not say which command
// each comes from. With a command of its own, the templates' command macros
// are left out and `ownCommandMacros` (those it was loaded with, while that
// command is unchanged) stand for them; without one, every template's are kept.
export const resolveInheritedMacros = ({
  hasCheckCommand,
  ownCommandMacros = [],
  templatesMacros
}: {
  hasCheckCommand: boolean;
  ownCommandMacros?: Array<MacroRow>;
  templatesMacros: Array<Array<MacroRow>>;
}): Array<MacroRow> => {
  const macros = templatesMacros.flat();
  const candidates = [
    ...macros.filter(({ parent }) => parent === 'template'),
    ...(hasCheckCommand
      ? ownCommandMacros
      : macros.filter(({ parent }) => parent === 'command'))
  ];

  return candidates.filter(
    ({ name }, index) =>
      candidates.findIndex((candidate) => candidate.name === name) === index
  );
};

// The rows once what the resource inherits has changed. Rows stay where they
// are: an untouched inherited row no longer inherited goes, a changed one
// becomes the resource's own (its source is gone), and newly inherited macros
// are added at the end unless a row already holds their name.
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
            isErased: false,
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
