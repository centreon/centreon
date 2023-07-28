import { Meta, StoryObj } from '@storybook/react';

import { ResourceNode } from './ResourceNode';
import { NodeSize, nodeSizes } from './ResourceNode.styles';
import { HostGroupResourceName, ResourceStatus } from '../Resource.resource';

const meta: Meta<typeof ResourceNode> = {
  component: ResourceNode,
  parameters: {
    layout: 'centered'
  }
};

export default meta;
type Story = StoryObj<typeof ResourceNode>;


export const Default: Story = {
  args: {
    node: {
      x: nodeSizes.default.height / 2,
      y: nodeSizes.default.width / 2,
      data: {
        name: 'node name',
        status: 'ok',
        group: 'server'
      }
    }
  },
  decorators: [
    (story) => <svg width={nodeSizes.default.width} height={nodeSizes.default.height}>{story()}</svg>
  ]
};

export const SizeVariants: Story = {
  args: Default.args,
  decorators: [
    (story) => <div style={{display: 'flex', flexDirection: 'column', gap: nodeSizes.default.height}}>{story()}</div>
  ],
  render: (args) => (
    <>
      {(['compact', 'default'] as NodeSize[]).map((size) => (
        <svg width={nodeSizes.default.width} height={nodeSizes.default.height}>
          <ResourceNode node={{
            ...args.node,
            data: {
              ...args.node.data,
              name: `${size} ${args.node.data.name}`
            },
            size: size
          }}/>
        </svg>
      ))}
    </>
  )
};

export const StatusVariants: Story = {
  args: Default.args,
  decorators: SizeVariants.decorators,
  render: (args) => (
    <>
      {(['neutral', 'ok', 'warn', 'error'] as ResourceStatus[]).map((status) => (
        <svg width={nodeSizes.default.width} height={nodeSizes.default.height}>
          <ResourceNode node={{
            ...args.node,
            data: {
              ...args.node.data,
              name: `${status} ${args.node.data.name}`,
              status: status
            }
          }}/>
        </svg>
      ))}
    </>
  )
};

export const GroupVariants: Story = {
  args: Default.args,
  decorators: SizeVariants.decorators,
  render: (args) => (
    <>
      {(['server', 'cloud', 'storage', 'router', 'firewall', 'other'] as HostGroupResourceName[]).map((group) => (
        <svg width={nodeSizes.default.width} height={nodeSizes.default.height}>
          <ResourceNode node={{
            ...args.node,
            data: {
              ...args.node.data,
              name: `${group} ${args.node.data.name}`,
              group: group
            }
          }}/>
        </svg>
      ))}
    </>
  )
};
