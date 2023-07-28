import { Meta, StoryObj } from '@storybook/react';

import { ResourceTree } from './ResourceTree';
import { rawTree } from './__fixtures__/resource.mock';

const meta: Meta<typeof ResourceTree> = {
  component: ResourceTree
};

export default meta;
type Story = StoryObj<typeof ResourceTree>;

export const Default: Story = {
  args: {
    data: rawTree
  }
};
