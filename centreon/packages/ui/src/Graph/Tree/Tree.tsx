import { useCallback, useMemo } from 'react';

import { Group } from '@visx/group';
import { Tree as VisxTree, hierarchy } from '@visx/hierarchy';
import { isNil } from 'ramda';

import { useDeepCompare } from '../../utils';

import DescendantNodes from './DescendantNodes';
import Links from './Links';
import { nodeMargins } from './constants';
import { BaseProp, Node, TreeProps } from './models';
import { updateNodeFromTree } from './utils';

export const Tree = <TData extends BaseProp>({
  containerHeight,
  containerWidth,
  tree,
  node,
  treeLink = {},
  changeTree,
  children
}: TreeProps<TData>): JSX.Element => {
  const formattedTree: Node<TData> = useMemo(
    () => ({
      ...tree,
      isExpanded: true
    }),
    useDeepCompare([tree])
  );

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

  const expandCollapseNode = useCallback(
    (targetNode: Node<TData>): void => {
      changeTree?.(
        toggleTreeNodesExpanded({ currentTree: formattedTree, targetNode })
      );
    },
    [formattedTree]
  );

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
    () => ({
      x: 0,
      y: containerHeight / 2
    }),
    [containerHeight]
  );

  return (
    <Group left={node.width}>
      <VisxTree
        left={0}
        nodeSize={[node.width + nodeMargins.y, node.height + nodeMargins.x]}
        root={hierarchy(formattedTree, getExpanded)}
        separation={() => 1}
        size={[containerWidth, containerHeight]}
        top={0}
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
