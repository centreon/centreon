/* eslint-disable react/no-array-index-key */
import { Meta, StoryObj } from '@storybook/react';

import { Typography } from '@mui/material';

import { SelectField } from '../..';

import { ItemComposition } from '.';

const meta: Meta<typeof ItemComposition> = {
  component: ItemComposition
};

export default meta;
type Story = StoryObj<typeof ItemComposition>;

const stub = (): void => undefined;

const items = [0, 1, 2, 3, 4];

export const Default: Story = {
  args: {
    children: items.map((item) => (
      <ItemComposition.Item key={item} labelDelete="Delete" onDeleteItem={stub}>
        <Typography>Item 1</Typography>
        <Typography>Item 2</Typography>
      </ItemComposition.Item>
    )),
    labelAdd: 'Add',
    onAddItem: stub
  }
};

export const Empty: Story = {
  args: {
    children: [],
    labelAdd: 'Add',
    onAddItem: stub
  }
};

const options = [
  {
    id: 1,
    name: 'Test select 1'
  },
  {
    id: 2,
    name: 'Test select 2'
  },
  {
    id: 3,
    name: 'Test select 3'
  }
];

export const WithSelectInputs: Story = {
  args: {
    children: items.map((i) => (
      <ItemComposition.Item key={i} labelDelete="Delete" onDeleteItem={stub}>
        <SelectField
          dataTestId="select 1"
          label="select 1"
          options={options}
          selectedOptionId={1}
          onChange={stub}
        />
        <SelectField
          dataTestId="select 2"
          label="select 2"
          options={options}
          selectedOptionId={2}
          onChange={stub}
        />
      </ItemComposition.Item>
    )),
    labelAdd: 'Add',
    onAddItem: stub
  }
};

export const WithLinkedItems: Story = {
  args: {
    children: items.map((i) => (
      <ItemComposition.Item key={i} labelDelete="Delete" onDeleteItem={stub}>
        <Typography>Item 1</Typography>
        <Typography>Item 2</Typography>
      </ItemComposition.Item>
    )),
    displayItemsAsLinked: true,
    labelAdd: 'Add',
    onAddItem: stub
  }
};
