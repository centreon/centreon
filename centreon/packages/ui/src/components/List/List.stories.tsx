/* eslint-disable react/no-array-index-key */
import { Meta, StoryObj } from '@storybook/react';

import DeleteIcon from '@mui/icons-material/Delete';
import { Box } from '@mui/material';

import { List } from '.';

const meta: Meta<typeof List> = {
  component: List
};

export default meta;
type Story = StoryObj<typeof List>;

const sampleData = Array(50).map((_, index) => ({
  key,
  index,
  icon: <DeleteIcon />,
  primary: 'Primary text',
  secondary: 'Secondary text'
}));

export const Default: Story = {
  render: () => (
    <List>
      {sampleData.map(({ primary, key }) => (
        <List.Item key={`${primary}_${key}`}>
          <List.Item.Text primaryText={primary} />
        </List.Item>
      ))}
    </List>
  )
};

export const withAction: Story = {
  render: () => (
    <List>
      {sampleData.map(({ icon, primary, key }) => (
        <List.Item action={icon} key={`${primary}_${key}`}>
          <List.Item.Text primaryText={primary} />
        </List.Item>
      ))}
    </List>
  )
};

export const withSecondaryText: Story = {
  render: () => (
    <List>
      {sampleData.map(({ primary, secondary, key }) => (
        <List.Item key={`${primary}_${key}`}>
          <List.Item.Text primaryText={primary} secondaryText={secondary} />
        </List.Item>
      ))}
    </List>
  )
};

export const withSmallContainer: Story = {
  render: () => (
    <Box sx={{ height: 400, width: 200 }}>
      <List>
        {sampleData.map(({ icon, primary, secondary, key }) => (
          <List.Item action={icon} key={`${primary}_${key}`}>
            <List.Item.Text primaryText={primary} secondaryText={secondary} />
          </List.Item>
        ))}
      </List>
    </Box>
  )
};
