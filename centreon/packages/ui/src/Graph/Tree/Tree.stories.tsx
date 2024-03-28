import { useState } from 'react';

import { Meta, StoryObj } from '@storybook/react';
import { equals, has } from 'ramda';

import { Zoom } from '../../components';

import {
  ComplexData,
  complexData,
  moreComplexData,
  SimpleData,
  simpleData
} from './stories/datas';
import { ComplexContent, SimpleContent } from './stories/contents';

import { StandaloneTree, Tree } from '.';

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

const TreeWithZoom = ({ tree, ...args }): JSX.Element => {
  const [currentTree, setTree] = useState(tree);

  return (
    <div style={{ height: '90vh', width: '100%' }}>
      <Zoom
        showMinimap
        labels={{
          clear: 'Clear'
        }}
      >
        {({ width, height }) => (
          <Tree<ComplexData>
            {...args}
            changeTree={setTree}
            containerHeight={height}
            containerWidth={width}
            tree={currentTree}
          />
        )}
      </Zoom>
    </div>
  );
};

export const DefaultStandaloneTree: Story = {
  args: {
    children: SimpleContent,
    node: {
      height: 90,
      width: 90
    },
    tree: simpleData
  },
  render: StandaloneTreeTemplate
};

export const WithDefaultExpandFilter: Story = {
  args: {
    children: SimpleContent,
    node: {
      height: 90,
      isDefaultExpanded: (data: SimpleData) => equals('critical', data.status),
      width: 90
    },
    tree: simpleData
  },
  render: StandaloneTreeTemplate
};

export const WithCustomLinks: Story = {
  args: {
    children: SimpleContent,
    node: {
      height: 90,
      width: 90
    },
    tree: simpleData,
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

export const WithResetButton: Story = {
  args: {
    children: SimpleContent,
    node: {
      height: 90,
      width: 90
    },
    tree: simpleData
  },
  render: StandaloneTreeTemplate
};

export const WithComplexData: Story = {
  args: {
    children: ComplexContent,
    node: {
      height: 90,
      isDefaultExpanded: (data: SimpleData) =>
        equals('critical', data.status) || !has('count', data),
      width: 90
    },
    tree: complexData,
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

export const treeWithZoom: Story = {
  args: {
    children: ComplexContent,
    node: {
      height: 90,
      isDefaultExpanded: (data: SimpleData) =>
        equals('critical', data.status) || !has('count', data),
      width: 90
    },
    tree: moreComplexData,
    treeLink: {
      getStroke: ({ target }) => (target.status === 'ok' ? 'grey' : 'black'),
      getStrokeDasharray: ({ target }) =>
        target.status === 'ok' ? '5,5' : '0',
      getStrokeOpacity: ({ target }) => (target.status === 'ok' ? 0.8 : 1),
      getStrokeWidth: ({ target }) => (target.status === 'ok' ? 1 : 2)
    }
  },
  render: TreeWithZoom
};
