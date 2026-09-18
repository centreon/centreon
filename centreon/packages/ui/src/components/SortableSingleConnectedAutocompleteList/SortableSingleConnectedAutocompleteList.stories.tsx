import ContentCopyIcon from '@mui/icons-material/ContentCopy';

import type { Meta, StoryObj } from '@storybook/react';
import { useState } from 'react';

import buildListingEndpoint from '../../api/buildListingEndpoint';
import type { Listing } from '../../api/models';
import type { SelectEntry } from '../../InputField/Select';
import type { GetEndpointParams } from '../../InputField/Select/Autocomplete/Connected';
import type {
  SortableAutocompleteAction,
  SortableAutocompleteEntry
} from './models';
import {
  SortableSingleConnectedAutocompleteList,
  type Props as SortableSingleConnectedAutocompleteListProps
} from './SortableSingleConnectedAutocompleteList';

const meta: Meta<typeof SortableSingleConnectedAutocompleteList> = {
  component: SortableSingleConnectedAutocompleteList
};

export default meta;
type Story = StoryObj<typeof SortableSingleConnectedAutocompleteList>;

const hostTemplateOptions: Array<SelectEntry> = [
  { id: 1, name: 'generic-active-host' },
  { id: 2, name: 'generic-passive-host' },
  { id: 3, name: 'generic-host-custom' },
  { id: 4, name: 'linux-server-standard' },
  { id: 5, name: 'windows-server-standard' }
];

const connectedEndpoint = '/configuration/hosts/templates';

const getEndpoint = (parameters: GetEndpointParams): string =>
  buildListingEndpoint({ baseEndpoint: connectedEndpoint, parameters });

const getConnectedMockData = (): Array<object> => [
  {
    delay: 300,
    method: 'GET',
    response: (): Listing<SelectEntry> => ({
      meta: { limit: 10, page: 1, total: hostTemplateOptions.length },
      result: hostTemplateOptions
    }),
    status: 200,
    url: `${connectedEndpoint}?page=`
  }
];

const buildInitialItems = (): Array<SortableAutocompleteEntry> => [
  { id: 'entry-1', value: hostTemplateOptions[0] },
  { id: 'entry-2', value: hostTemplateOptions[1] }
];

const ControlledSortableSingleConnectedAutocompleteList = (
  args: Omit<SortableSingleConnectedAutocompleteListProps, 'items' | 'onChange'>
): JSX.Element => {
  const [items, setItems] = useState<Array<SortableAutocompleteEntry>>(
    buildInitialItems()
  );

  return (
    <SortableSingleConnectedAutocompleteList
      {...args}
      items={items}
      onChange={setItems}
    />
  );
};

export const Playground: Story = {
  args: {
    baseEndpoint: '',
    field: 'name',
    getEndpoint,
    selectorLabel: 'Template'
  },
  parameters: {
    mockData: getConnectedMockData()
  },
  render: (args) => (
    <ControlledSortableSingleConnectedAutocompleteList {...args} />
  )
};

const buildActions = (
  onDuplicate: (entry: SortableAutocompleteEntry) => void
): Array<SortableAutocompleteAction> => [
  {
    color: 'blue',
    icon: <ContentCopyIcon fontSize="small" />,
    id: 'duplicate',
    label: 'Duplicate',
    onClick: onDuplicate
  }
];

const WithActionsExample = (): JSX.Element => {
  const [items, setItems] = useState<Array<SortableAutocompleteEntry>>(
    buildInitialItems()
  );

  const actions = buildActions((entry) =>
    setItems((current) => [
      ...current,
      { id: `${entry.id}-copy-${Date.now()}`, value: entry.value }
    ])
  );

  return (
    <SortableSingleConnectedAutocompleteList
      actions={actions}
      baseEndpoint=""
      field="name"
      getEndpoint={getEndpoint}
      items={items}
      onChange={setItems}
      selectorLabel="Template"
    />
  );
};

export const WithActions: Story = {
  parameters: {
    mockData: getConnectedMockData()
  },
  render: () => <WithActionsExample />
};

export const WithoutActions: Story = {
  args: {
    actions: [],
    baseEndpoint: '',
    field: 'name',
    getEndpoint,
    selectorLabel: 'Template'
  },
  parameters: {
    mockData: getConnectedMockData()
  },
  render: (args) => (
    <ControlledSortableSingleConnectedAutocompleteList {...args} />
  )
};

export const Empty: Story = {
  parameters: {
    mockData: getConnectedMockData()
  },
  render: () => {
    const Wrapper = (): JSX.Element => {
      const [items, setItems] = useState<Array<SortableAutocompleteEntry>>([]);

      return (
        <SortableSingleConnectedAutocompleteList
          baseEndpoint=""
          field="name"
          getEndpoint={getEndpoint}
          items={items}
          onChange={setItems}
          selectorLabel="Template"
        />
      );
    };

    return <Wrapper />;
  }
};
