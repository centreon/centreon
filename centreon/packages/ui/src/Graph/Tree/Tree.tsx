import { useCallback, useMemo, useState } from 'react';

import { Group } from '@visx/group';
import { hierarchy, Tree as VisxTree } from '@visx/hierarchy';
import { isNil } from 'ramda';

import { useDeepCompare } from '../../utils';

import { margins, nodeMargins } from './constants';
import { BaseProp, Node, TreeProps } from './models';
import { updateNodeFromTree } from './utils';
import Links from './Links';
import DescendantNodes from './DescendantNodes';

export const Tree = <TData extends BaseProp>({
  containerHeight,
  containerWidth,
  data,
  node,
  treeLink = {},
  children
}: TreeProps<TData>): JSX.Element => {
  const formattedData: Node<TData> = useMemo(
    () => ({
      ...data,
      isExpanded: true
    }),
    useDeepCompare([data])
  );

  const [tree, setTree] = useState(formattedData);

  const toggleTreeNodesExpanded = useCallback(
    ({ currentTree, targetNode }): Node<TData> => {
      return updateNodeFromTree({
        callback: (subTree) => {
          if (isNil(subTree.isExpanded) && isNil(node.isDefaultExpanded)) {
            return {
              isExpanded: false
            };
          }

          return {
            isExpanded: isNil(subTree.isExpanded)
              ? !node.isDefaultExpanded?.(subTree.data)
              : !subTree.isExpanded || false
          };
        },
        targetNode,
        tree: currentTree
      });
    },
    [node.isDefaultExpanded]
  );

  const expandCollapseNode = useCallback((targetNode: Node<TData>): void => {
    setTree((currentTree) => {
      return toggleTreeNodesExpanded({ currentTree, targetNode });
    });
  }, []);

  const getExpanded = useCallback(
    (d: Node<TData>): Array<Node<TData>> | undefined | null => {
      if (isNil(d.isExpanded) && isNil(node.isDefaultExpanded)) {
        return d.children;
      }
      if (isNil(d.isExpanded)) {
        return node.isDefaultExpanded?.(d.data) ? d.children : null;
      }

      return d.isExpanded ? d.children : null;
    },
    [node.isDefaultExpanded]
  );

  const origin = useMemo(
    () => ({ x: 0, y: containerHeight / 2 }),
    [containerHeight]
  );

  return (
    <Group left={node.width / 2} top={margins.top}>
      <VisxTree
        nodeSize={[node.width + nodeMargins.y, node.height + nodeMargins.x]}
        root={hierarchy(tree, getExpanded)}
        separation={() => 1}
        size={[containerWidth, containerHeight]}
      >
        {(subTree) => (
          <Group left={origin.x} top={origin.y}>
            <Links links={subTree.links()} treeLink={treeLink} />
            <DescendantNodes
              descendants={subTree.descendants()}
              expandCollapseNode={expandCollapseNode}
              getExpanded={getExpanded}
              nodeSize={{
                height: node.height,
                width: node.width
              }}
            >
              {children}
            </DescendantNodes>
          </Group>
        )}
      </VisxTree>
    </Group>
  );
};
