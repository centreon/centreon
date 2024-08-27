import { useState } from 'react';

import { ParentSize } from '../..';

import { Tree } from './Tree';
import { BaseProp, TreeProps } from './models';

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
