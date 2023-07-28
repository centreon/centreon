import { ReactElement, useMemo } from 'react';

import { hierarchy, Tree } from '@visx/hierarchy';

import { Size, VizSize } from '../DataViz.types';

import { TreeNode } from './ResourceTree.types';
import { ResourceNode } from './Node/ResourceNode';
import { ResourceLink } from './Link/ResourceLink';

type ResourceTreeProps = {
  data: TreeNode; // | Resource[];
  size?: VizSize;
  nodeSize?: Size;
};

const ResourceTree = ({
  data,
  size = {height: 540, width: 1600}
}: ResourceTreeProps): ReactElement => {

  const hierarchicalData = useMemo(() => hierarchy(data), []);

  const {width, height, margin = {bottom: 0, left: 100, right: 100, top: 0}} = size;
  const yMax = height - margin.top - margin.bottom;
  const xMax = width - margin.left - margin.right;

  return (
    <div className="resource-tree">
      <svg height={height} width={width}>
        <Tree<TreeNode>
          root={hierarchicalData}
          size={[yMax, xMax]}
          // nodeSize={[26 * 1.5, 176 * 1.5]}
          top={margin.top}
          left={margin.left}
          linkComponent={ResourceLink}
          nodeComponent={ResourceNode}
        />
      </svg>
    </div>
  );
};

export { ResourceTree };
