import { ParentSize } from '../..';

import { BaseProp, TreeProps } from './models';
import { Tree } from './Tree';

export const StandaloneTree = <TData extends BaseProp>(
  props: Omit<TreeProps<TData>, 'containerHeight' | 'containerWidth'>
): JSX.Element => (
  <ParentSize>
    {({ width, height }) => (
      <svg height={height} width={width}>
        <Tree {...props} containerHeight={height} containerWidth={width} />
      </svg>
    )}
  </ParentSize>
);
