import DescriptionOutlinedIcon from '@mui/icons-material/DescriptionOutlined';
import EditIcon from '@mui/icons-material/Edit';
import VisibilityIcon from '@mui/icons-material/Visibility';

import { useState } from 'react';

import { TestQueryProvider } from '../..';
import buildListingEndpoint from '../../api/buildListingEndpoint';
import type { SelectEntry } from '../../InputField/Select';
import SingleConnectedAutocompleteField from '../../InputField/Select/Autocomplete/Connected/Single';
import TextField from '../../InputField/Text';
import type { RenderRowParams, RowParams, SortableRowAction } from './models';
import { SortableEntriesList } from './SortableEntriesList';

// The specs read the last values reported by onChange, and the other calls.
const Recorder = ({
  calls,
  values
}: {
  calls?: unknown;
  values: unknown;
}): JSX.Element => (
  <>
    <output data-testid="last-values">{JSON.stringify(values)}</output>
    <output data-testid="calls">{JSON.stringify(calls ?? null)}</output>
  </>
);

type TemplateRow = SelectEntry | null;

const templateOptions: Array<SelectEntry> = [
  { id: 1, name: 'generic-active-host' },
  { id: 2, name: 'generic-passive-host' },
  { id: 3, name: 'linux-server-standard' }
];

const renderTemplateRow = ({
  setValue,
  value,
  values
}: RenderRowParams<TemplateRow>): JSX.Element => {
  const selectedElsewhere = values
    .map((template) => template?.id)
    .filter((id) => id !== value?.id);

  return (
    <SingleConnectedAutocompleteField
      baseEndpoint=""
      field="name"
      fullWidth
      getEndpoint={(parameters): string =>
        buildListingEndpoint({
          baseEndpoint: '/configuration/hosts/templates',
          parameters
        })
      }
      getOptionDisabled={(option: SelectEntry): boolean =>
        selectedElsewhere.includes(option.id)
      }
      label="Template"
      onChange={(_event, template): void => setValue(template as TemplateRow)}
      value={value}
    />
  );
};

interface TemplatesProps {
  draggable?: boolean;
  templates?: number;
}

export const Templates = ({
  draggable = true,
  templates = 2
}: TemplatesProps): JSX.Element => {
  const [values, setValues] = useState<Array<TemplateRow>>(
    templateOptions.slice(0, templates)
  );
  const [reported, setReported] = useState<unknown>(null);
  const [edits, setEdits] = useState<Array<unknown>>([]);

  return (
    <TestQueryProvider>
      <div className="p-4">
        <SortableEntriesList<TemplateRow>
          actions={({
            index,
            value
          }: RowParams<TemplateRow>): Array<SortableRowAction> => [
            {
              icon: <EditIcon />,
              id: 'edit-template',
              label: 'Edit template',
              onClick: () => setEdits((current) => [...current, [value, index]])
            }
          ]}
          createValue={(): TemplateRow => null}
          draggable={draggable}
          label="Templates"
          onChange={(nextValues): void => {
            setReported(nextValues);
            setValues(nextValues);
          }}
          renderRow={renderTemplateRow}
          values={values}
        />
        <Recorder calls={edits} values={reported} />
      </div>
    </TestQueryProvider>
  );
};

interface MacroRow {
  isPassword: boolean;
  name: string;
  origin: 'direct' | 'fromTpl';
  value: string;
}

const emptyMacro: MacroRow = {
  isPassword: false,
  name: '',
  origin: 'direct',
  value: ''
};

const renderMacroRow = ({
  setField,
  value: macro
}: RenderRowParams<MacroRow>): JSX.Element => (
  <div className="grid grid-cols-2 gap-2 @max-[600px]:grid-cols-1">
    <TextField
      dataTestId="macro-name"
      fullWidth
      label="Name"
      onChange={(event): void => setField('name', event.target.value)}
      value={macro.name}
    />
    <TextField
      dataTestId="macro-value"
      fullWidth
      label="Value"
      onChange={(event): void => setField('value', event.target.value)}
      type={macro.isPassword ? 'password' : 'text'}
      value={macro.value}
    />
  </div>
);

export const Macros = ({
  copyOnChange = false
}: {
  copyOnChange?: boolean;
}): JSX.Element => {
  const [values, setValues] = useState<Array<MacroRow>>([
    { isPassword: false, name: 'MACRO_A', origin: 'direct', value: 'a' },
    { isPassword: true, name: 'MACRO_B', origin: 'fromTpl', value: 'b' }
  ]);
  const [reported, setReported] = useState<unknown>(null);
  const change = (nextValues: Array<MacroRow>): void => {
    setReported(nextValues);
    // A parent storing a copy, like a form library cloning its state.
    setValues(
      copyOnChange ? nextValues.map((macro) => ({ ...macro })) : nextValues
    );
  };

  return (
    <>
      <button
        data-testid="reset"
        onClick={(): void => setValues([emptyMacro])}
        type="button"
      >
        Reset
      </button>
      <SortableEntriesList<MacroRow>
        actions={({ index, value }) => [
          {
            icon: <VisibilityIcon />,
            id: 'toggle-password',
            label: 'Toggle password',
            onClick: () =>
              change(
                values.map((macro, macroIndex) =>
                  macroIndex === index
                    ? { ...macro, isPassword: !value.isPassword }
                    : macro
                )
              )
          }
        ]}
        createValue={(): MacroRow => emptyMacro}
        getRowClassName={({ value }) => `origin-${value.origin}`}
        onChange={change}
        renderRow={renderMacroRow}
        values={values}
      />
      <Recorder values={reported} />
    </>
  );
};

const panelWidths = [1400, 900, 720, 560];

interface PanelProps {
  count: number;
  maxVisibleRows?: number;
}

// The panel width is state: its buttons resize the mounted list.
export const Panel = ({ count, maxVisibleRows }: PanelProps): JSX.Element => {
  const [width, setWidth] = useState(panelWidths[0]);
  const [values, setValues] = useState<Array<MacroRow>>(
    Array.from({ length: count }, (_, index) => ({
      ...emptyMacro,
      name: `MACRO_${index + 1}`
    }))
  );
  const [reported, setReported] = useState<unknown>(null);

  return (
    <div className="p-4">
      {panelWidths.map((panelWidth) => (
        <button
          data-testid={`width-${panelWidth}`}
          key={panelWidth}
          onClick={(): void => setWidth(panelWidth)}
          type="button"
        >
          {panelWidth}
        </button>
      ))}
      <div data-testid="panel" style={{ width }}>
        <SortableEntriesList<MacroRow>
          actions={(): Array<SortableRowAction> => [
            {
              icon: <VisibilityIcon />,
              id: 'toggle-password',
              label: 'Toggle password',
              onClick: () => undefined
            },
            {
              icon: <DescriptionOutlinedIcon />,
              id: 'macro-description',
              label: 'Description',
              onClick: () => undefined
            }
          ]}
          createValue={(): MacroRow => emptyMacro}
          maxVisibleRows={maxVisibleRows}
          onChange={(nextValues): void => {
            setReported(nextValues);
            setValues(nextValues);
          }}
          renderRow={renderMacroRow}
          values={values}
        />
      </div>
      <Recorder values={reported} />
    </div>
  );
};
