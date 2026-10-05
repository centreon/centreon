import DescriptionOutlinedIcon from '@mui/icons-material/DescriptionOutlined';
import EditIcon from '@mui/icons-material/Edit';
import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';
import VisibilityIcon from '@mui/icons-material/Visibility';
import VisibilityOffIcon from '@mui/icons-material/VisibilityOff';

import type { Meta, StoryObj } from '@storybook/react';
import { useState } from 'react';

import buildListingEndpoint from '../../api/buildListingEndpoint';
import type { Listing } from '../../api/models';
import type { SelectEntry } from '../../InputField/Select';
import type { GetEndpointParams } from '../../InputField/Select/Autocomplete/Connected';
import SingleConnectedAutocompleteField from '../../InputField/Select/Autocomplete/Connected/Single';
import TextField from '../../InputField/Text';
import type { RenderRowParams } from './models';
import {
  SortableEntriesList,
  type Props as SortableEntriesListProps
} from './SortableEntriesList';

const meta: Meta<typeof SortableEntriesList> = {
  component: SortableEntriesList
};

export default meta;
type Story = StoryObj<typeof SortableEntriesList>;

const ValuesPreview = ({ values }: { values: Array<unknown> }): JSX.Element => (
  <pre className="mt-4 rounded bg-background-default p-2 text-text-primary text-xs">
    {JSON.stringify(values, null, 2)}
  </pre>
);

// Templates: one connected autocomplete per row, order matters (inheritance)

type TemplateRow = SelectEntry | null;

const hostTemplateOptions: Array<SelectEntry> = [
  { id: 1, name: 'generic-active-host' },
  { id: 2, name: 'generic-passive-host' },
  { id: 3, name: 'generic-host-custom' },
  { id: 4, name: 'linux-server-standard' },
  { id: 5, name: 'windows-server-standard' }
];

const templatesEndpoint = '/configuration/hosts/templates';

const getTemplatesEndpoint = (parameters: GetEndpointParams): string =>
  buildListingEndpoint({ baseEndpoint: templatesEndpoint, parameters });

const templatesMockData = [
  {
    delay: 300,
    method: 'GET',
    response: (): Listing<SelectEntry> => ({
      meta: { limit: 10, page: 1, total: hostTemplateOptions.length },
      result: hostTemplateOptions
    }),
    status: 200,
    url: `${templatesEndpoint}?page=`
  }
];

const createTemplateRow = (): TemplateRow => null;

const renderTemplateRow = ({
  setValue,
  value,
  values
}: RenderRowParams<TemplateRow>): JSX.Element => {
  // A template already chosen in another row cannot be chosen twice.
  const selectedElsewhere = values
    .map((template) => template?.id)
    .filter((id) => id !== value?.id);

  return (
    <SingleConnectedAutocompleteField
      baseEndpoint=""
      field="name"
      fullWidth
      getEndpoint={getTemplatesEndpoint}
      getOptionDisabled={(option: SelectEntry): boolean =>
        selectedElsewhere.includes(option.id)
      }
      label="Select a template"
      onChange={(_event, template): void => setValue(template as TemplateRow)}
      value={value}
    />
  );
};

type TemplatesListArgs = Partial<
  Pick<
    SortableEntriesListProps<TemplateRow>,
    'addLabel' | 'draggable' | 'maxVisibleRows'
  >
>;

const TemplatesList = (args: TemplatesListArgs): JSX.Element => {
  const [templates, setTemplates] = useState<Array<TemplateRow>>([
    hostTemplateOptions[0],
    hostTemplateOptions[3]
  ]);

  return (
    <div className="w-[640px]">
      <SortableEntriesList<TemplateRow>
        actions={({ value }) => [
          {
            icon: <EditIcon />,
            id: 'edit-template',
            isDisabled: !value,
            label: 'Edit template',
            onClick: () => undefined
          },
          {
            icon: <InfoOutlinedIcon />,
            id: 'template-info',
            isDisabled: !value,
            label: 'Template information',
            onClick: () => undefined
          }
        ]}
        addLabel="Add new entry"
        createValue={createTemplateRow}
        label="Templates"
        onChange={setTemplates}
        renderRow={renderTemplateRow}
        values={templates}
        {...args}
      />
      <ValuesPreview values={templates} />
    </div>
  );
};

export const Templates: Story = {
  args: { draggable: true, maxVisibleRows: 10 },
  argTypes: {
    addLabel: { control: 'text' },
    draggable: { control: 'boolean' },
    maxVisibleRows: { control: 'number' }
  },
  parameters: { mockData: templatesMockData },
  render: (args) => <TemplatesList {...(args as TemplatesListArgs)} />
};

// Custom macros: several inputs per row, row styled from its own value

interface MacroRow {
  isPassword: boolean;
  name: string;
  origin: 'direct' | 'fromCommand' | 'fromTpl';
  value: string;
}

const createMacroRow = (): MacroRow => ({
  isPassword: false,
  name: '',
  origin: 'direct',
  value: ''
});

const macroRowClassName: Record<MacroRow['origin'], string> = {
  direct: 'rounded p-1',
  fromCommand: 'rounded bg-teal-50 p-1 dark:bg-teal-950',
  fromTpl: 'rounded bg-purple-50 p-1 dark:bg-purple-950'
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

const initialMacros: Array<MacroRow> = [
  { isPassword: false, name: 'HOST_IP', origin: 'direct', value: '10.0.0.1' },
  {
    isPassword: true,
    name: 'API_TOKEN',
    origin: 'fromTpl',
    value: 'secret-token'
  },
  { isPassword: false, name: 'PORT', origin: 'fromCommand', value: '443' }
];

const buildMacros = (count: number): Array<MacroRow> =>
  Array.from({ length: count }, (_, index) => ({
    isPassword: false,
    name: `MACRO_${index + 1}`,
    origin: 'direct',
    value: `value ${index + 1}`
  }));

const MacrosList = ({
  draggable,
  initialValues = initialMacros,
  maxVisibleRows
}: {
  draggable: boolean;
  initialValues?: Array<MacroRow>;
  maxVisibleRows?: number;
}): JSX.Element => {
  const [macros, setMacros] = useState(initialValues);

  const toggleIsPassword = (index: number): void =>
    setMacros((current) =>
      current.map((macro, macroIndex) =>
        macroIndex === index
          ? { ...macro, isPassword: !macro.isPassword }
          : macro
      )
    );

  return (
    <>
      <SortableEntriesList<MacroRow>
        actions={({ index, value }) => [
          {
            icon: value.isPassword ? <VisibilityOffIcon /> : <VisibilityIcon />,
            id: 'toggle-password',
            label: value.isPassword ? 'Show value' : 'Hide value',
            onClick: () => toggleIsPassword(index)
          },
          {
            icon: <DescriptionOutlinedIcon />,
            id: 'macro-description',
            label: 'Description',
            onClick: () => undefined
          }
        ]}
        createValue={createMacroRow}
        draggable={draggable}
        getRowClassName={({ value }) => macroRowClassName[value.origin]}
        label="Custom macros"
        maxVisibleRows={maxVisibleRows}
        onChange={setMacros}
        renderRow={renderMacroRow}
        values={macros}
      />
      <ValuesPreview values={macros} />
    </>
  );
};

export const CustomMacros: Story = {
  render: () => (
    <div className="w-[640px]">
      <MacrosList draggable />
    </div>
  )
};

export const NonDraggable: Story = {
  render: () => (
    <div className="w-[640px]">
      <MacrosList draggable={false} />
    </div>
  )
};

export const Empty: Story = {
  render: () => (
    <div className="w-[640px]">
      <MacrosList draggable initialValues={[]} />
    </div>
  )
};

// Capped list in a resizable container: drag the bottom-right corner or use the
// presets to check the row layout against the list width, not the viewport.
const panelWidths = [1400, 900, 720, 560];

const ScrollableMacrosList = (): JSX.Element => {
  const [width, setWidth] = useState(panelWidths[0]);

  return (
    <div className="flex flex-col gap-2">
      <div className="flex gap-2">
        {panelWidths.map((panelWidth) => (
          <button
            className="rounded border px-2 py-1"
            key={panelWidth}
            onClick={(): void => setWidth(panelWidth)}
            type="button"
          >
            {panelWidth}px
          </button>
        ))}
      </div>
      <div
        className="max-w-full resize-x overflow-auto border border-dashed p-2"
        style={{ width }}
      >
        <MacrosList
          draggable
          initialValues={buildMacros(15)}
          maxVisibleRows={10}
        />
      </div>
    </div>
  );
};

export const ScrollableAndResizable: Story = {
  render: () => <ScrollableMacrosList />
};
