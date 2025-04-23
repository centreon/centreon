import { useState } from 'react';

import { Group } from '@visx/group';
import { HierarchyPointNode } from '@visx/hierarchy/lib/types';
import { gt, isNil, pluck } from 'ramda';

import { BaseProp, Node, TreeProps } from './models';

interface Props<TData> extends Pick<TreeProps<TData>, 'children'> {
  descendants: Array<HierarchyPointNode<Node<TData>>>;
  expandCollapseNode: (targetNode: Node<TData>) => void;
  getExpanded: (d: Node<TData>) => Array<Node<TData>> | undefined;
  nodeSize: {
    height: number;
    width: number;
  };
}

const DescendantNodes = <TData extends BaseProp>({
  descendants,
  children,
  expandCollapseNode,
  getExpanded,
  nodeSize
}: Props<TData>): Array<JSX.Element> => {
  const [pressEventTimeStamp, setPressEventTimeStamp] = useState<number | null>(
    null
  );

  const mouseDown = (e: MouseEvent): void => {
    setPressEventTimeStamp(e.timeStamp);
  };

  const mouseUp =
    (callback) =>
    (e: MouseEvent): void => {
      if (isNil(pressEventTimeStamp)) {
        callback();

        return;
      }

      const diffTimeStamp = e.timeStamp - pressEventTimeStamp;

      if (gt(diffTimeStamp, 120)) {
        return;
      }

      callback();
    };

  return descendants.map((node) => {
    const top = node.x;
    const left = node.y;
    const ancestorIds = node
      .ancestors()
      .map((ancestor) => ancestor.data.data.id);
    const descendantIds = node
      .descendants()
      .map((ancestor) => ancestor.data.data.id);

    const key = `${node.data.data.id}-${node.data.data.name}-${ancestorIds.toString()}-${descendantIds.toString()}`;

    return (
      <Group key={key} left={left} top={top}>
        <foreignObject
          height={nodeSize.height}
          style={{ userSelect: 'none' }}
          width={nodeSize.width}
          x={-nodeSize.width / 2}
          y={-nodeSize.height / 2}
        >
          {children({
            ancestors: pluck('data', node.ancestors()),
            depth: node.depth,
            expandCollapseNode,
            isExpanded: !!getExpanded(node.data),
            node: node.data,
            nodeSize,
            onMouseDown: mouseDown,
            onMouseUp: mouseUp
          })}
        </foreignObject>
      </Group>
    );
  });
};

export default DescendantNodes;
