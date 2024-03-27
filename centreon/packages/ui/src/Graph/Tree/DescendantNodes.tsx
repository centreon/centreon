import { HierarchyPointNode } from '@visx/hierarchy/lib/types';
import { Group } from '@visx/group';
import { pluck } from 'ramda';

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
        {children({
          ancestors: pluck('data', node.ancestors()),
          depth: node.depth,
          expandCollapseNode,
          isExpanded: !!getExpanded(node.data),
          node: node.data,
          nodeSize
        })}
      </Group>
    );
  });
};

export default DescendantNodes;
