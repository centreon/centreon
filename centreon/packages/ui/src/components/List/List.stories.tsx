import { Meta, StoryObj } from '@storybook/react';

import DeleteIcon from '@mui/icons-material/Delete';
import { Box } from '@mui/material';

import { List } from '.';

const meta: Meta<typeof List> = {
  component: List
};

export default meta;
type Story = StoryObj<typeof List>;

const sampleData = Array(50).fill({
  icon: <DeleteIcon />,
  primary: 'Primary text',
  secondary: 'Secondary text'
});

export const Default: Story = {
  render: () => (
    <List>
      {sampleData.map(({ primary }, index) => (
        <List.Item key={`${primary}_${index}`}>
          <List.ItemText primaryText={primary} />
        </List.Item>
      ))}
    </List>
  )
};

export const withAction: Story = {
  render: () => (
    <List>
      {sampleData.map(({ icon, primary }, index) => (
        <List.Item action={icon} key={`${primary}_${index}`}>
          <List.ItemText primaryText={primary} />
        </List.Item>
      ))}
    </List>
  )
};

export const withSecondaryText: Story = {
  render: () => (
    <List>
      {sampleData.map(({ primary, secondary }, index) => (
        <List.Item key={`${primary}_${index}`}>
          <List.ItemText primaryText={primary} secondaryText={secondary} />
        </List.Item>
      ))}
    </List>
  )
};

export const withSmallContainer: Story = {
  render: () => (
    <Box sx={{ height: 400, width: 200 }}>
      <List>
        {sampleData.map(({ icon, primary, secondary }, index) => (
          <List.Item action={icon} key={`${primary}_${index}`}>
            <List.ItemText primaryText={primary} secondaryText={secondary} />
          </List.Item>
        ))}
      </List>
    </Box>
  )
};
