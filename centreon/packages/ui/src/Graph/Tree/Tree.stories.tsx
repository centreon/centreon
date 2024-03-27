import { Meta, StoryObj } from '@storybook/react';
import { equals } from 'ramda';

import { SimpleData, simpleData } from './stories/datas';
import { SimpleContent } from './stories/contents';

import { StandaloneTree } from '.';

const meta: Meta<typeof StandaloneTree> = {
  component: StandaloneTree
};

export default meta;
type Story = StoryObj<typeof StandaloneTree>;

const StandaloneTreeTemplate = (args): JSX.Element => (
  <div style={{ height: '90vh', width: '100%' }}>
    <StandaloneTree<SimpleData> {...args} />
  </div>
);

export const DefaultStandaloneTree: Story = {
  args: {
    children: SimpleContent,
    data: simpleData,
    node: {
      height: 90,
      width: 90
    }
  },
  render: StandaloneTreeTemplate
};

export const WithDefaultExpandFilter: Story = {
  args: {
    children: SimpleContent,
    data: simpleData,
    node: {
      height: 90,
      isDefaultExpanded: (data: SimpleData) => equals('critical', data.status),
      width: 90
    }
  },
  render: StandaloneTreeTemplate
};

export const WithCustomLinks: Story = {
  args: {
    children: SimpleContent,
    data: simpleData,
    node: {
      height: 90,
      width: 90
    },
    treeLink: {
      getStroke: ({ target }) => (target.status === 'ok' ? 'grey' : 'black'),
      getStrokeDasharray: ({ target }) =>
        target.status === 'ok' ? '5,5' : '0',
      getStrokeOpacity: ({ target }) => (target.status === 'ok' ? 0.8 : 1),
      getStrokeWidth: ({ target }) => (target.status === 'ok' ? 1 : 2)
    }
  },
  render: StandaloneTreeTemplate
};
