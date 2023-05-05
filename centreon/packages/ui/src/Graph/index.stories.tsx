import { Meta, StoryObj } from '@storybook/react';

import AwesomeTimePeriod from '../TimePeriods';

import { argTypes, args as argumentsData } from './helpers/doc';

import Graph from './index';

const meta: Meta<typeof Graph> = {
  component: Graph,
  tags: ['autodocs']
};
export default meta;

type Story = StoryObj<typeof Graph>;

const Template: Story = {
  render: (args) => (
    <>
      <AwesomeTimePeriod />
      <Graph {...args} />
    </>
  )
};

export const Playground: Story = {
  ...Template,
  argTypes,
  args: argumentsData
};
