import { equals, omit } from 'ramda';

import { BaseProp, Node } from './models';

interface UpdateNodeFromTreeProps<TData> {
  callback: (tree: Node<TData>) => Partial<Node<TData>>;
  targetNode: Node<TData>;
  tree: Node<TData>;
}

export const updateNodeFromTree = <TData extends BaseProp>({
  tree,
  targetNode,
  callback
}: UpdateNodeFromTreeProps<TData>): Node<TData> => {
  if (!tree.children) {
    return tree;
  }

  if (
    equals(tree.data, targetNode.data) &&
    equals(tree.children, targetNode.children)
  ) {
    return {
      ...tree,
      ...callback(tree)
    };
  }

  return {
    ...tree,
    children: tree.children?.map((child) =>
      updateNodeFromTree({ callback, targetNode, tree: child })
    )
  };
};

export const cleanUpTree = <TData extends BaseProp>(
  tree: Node<TData>
): Node<TData> => {
  if (!tree.children) {
    return tree;
  }

  return {
    ...omit(['isExpanded'], tree),
    children: tree.children?.map((child) => cleanUpTree(child))
  };
};
