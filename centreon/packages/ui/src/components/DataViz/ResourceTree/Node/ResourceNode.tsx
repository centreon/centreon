import React, { ReactElement } from 'react';

import { Group } from '@visx/group';
import { HierarchyPointNode } from '@visx/hierarchy/lib/types';

import { TreeNode } from '../ResourceTree.types';
import { NodeSize, useStyles } from './ResourceNode.styles';
import { HostGroupTypeIcon } from '../common/HostGroupTypeIcon';

type ResourceNodeProps = {
  node: {
      x: HierarchyPointNode<TreeNode>['x'],
      y: HierarchyPointNode<TreeNode>['y'],
      data: HierarchyPointNode<TreeNode>['data'],
      align?: 'start' | 'center',
      size?: NodeSize,
    }
    & Partial<Omit<HierarchyPointNode<TreeNode>, 'data' | 'x' | 'y'>>;
};

const ResourceNode = ({node}: ResourceNodeProps): ReactElement => {
  const {align = 'start', size = 'default'} = node;
  const {classes} = useStyles();

  return (
    <Group
      left={node.y}
      top={node.x}
      className={classes.resourceNode}
      data-align={align}
      data-size={size}
      data-status={node.data.status}
    >
      <mask id={`connectors-mask-${size}`} className={'connectors-mask'}>
        <rect className={'bg'}/>
        <circle className={'connector'}/>
        <circle className={'connector'}/>
      </mask>
      <rect className={'bg'} mask={`url(#connectors-mask-${size})`}/>
      {size !== 'compact' &&
        <foreignObject className={'label'}>
          <div><span>{node.data.name}</span></div>
        </foreignObject>
      }
      <Group className={'indicators'}>
        {node.data.group && <HostGroupTypeIcon className={'group'} type={node.data.group}/>}
        <circle className={'status'}/>
      </Group>
    </Group>
  );
};

export { ResourceNode };
