import { ComponentMeta, ComponentStory } from '@storybook/react';

import { Typography } from '@mui/material';

import Responsive from '.';

export default {
  argTypes: {
    count: { margin: 'number' }
  },
  component: Responsive,
  title: 'Responsive'
} as ComponentMeta<typeof Responsive>;

const Story: ComponentStory<typeof Responsive> = (args) => (
  <div style={{ height: '100%', width: '100%' }}>
    <Responsive {...args}>
      <Typography variant="h1">Hello</Typography>
      <Typography variant="h1">Hello</Typography>
      <Typography variant="h1">Hello</Typography>
      <Typography variant="h1">Hello</Typography>
      <Typography variant="h1">Hello</Typography>
      <Typography variant="h1">Hello</Typography>
      <Typography variant="h1">Hello</Typography>
      <Typography variant="h1">Hello</Typography>
    </Responsive>
  </div>
);

export const Playground = Story.bind({});
Playground.args = {
  margin: 0
};
