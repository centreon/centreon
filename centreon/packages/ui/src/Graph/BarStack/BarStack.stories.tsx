import { Meta, StoryObj } from '@storybook/react';

import BarVerical from './BarVerical';

import { BarStack } from '.';

const meta: Meta<typeof BarStack> = {
  component: BarStack
};

export default meta;
type Story = StoryObj<typeof BarStack>;

const Template = (args): JSX.Element => {
  return (
    <div style={{ height: '50px', width: '500px' }}>
      <BarStack {...args} />
    </div>
  );
};

export const Normal: Story = {
  args: {},
  render: Template
};

const Vericaltemplate = (args): JSX.Element => {
  return <BarVerical {...args} />;
};
export const Vertical: Story = {
  args: { height: 400, width: 60 },
  render: Vericaltemplate
};
