import { useState } from 'react';

import { ParentSize } from '../..';
import type { BaseProp, TreeProps } from './models';
import { Tree } from './Tree';

export const StandaloneTree = <TData extends BaseProp>({
  tree,
  ...props
}: Omit<
  TreeProps<TData>,
  'containerHeight' | 'containerWidth'
>): JSX.Element => {
  const [currentTree, setTree] = useState(tree);

  return (
    <ParentSize>
      {({ width, height }) => (
        <svg height={height} width={width}>
          <title>Tree</title>
          <Tree
            {...props}
            changeTree={setTree}
            containerHeight={height}
            containerWidth={width}
            tree={currentTree}
          />
        </svg>
      )}
    </ParentSize>
  );
};
