export interface Node<T> {
  children?: Array<Node<T>>;
  data: T;
  isExpanded?: boolean;
}

export interface LinkProps<T> {
  source: T;
  target: T;
}

export interface BaseProp {
  id: number;
  name: string;
}

export interface ChildrenProps<TData> {
  ancestors: Array<Node<TData>>;
  depth: number;
  expandCollapseNode: (targetNode: Node<TData>) => void;
  isExpanded: boolean;
  node: Node<TData>;
  nodeSize: {
    height: number;
    width: number;
  };
}

export interface TreeProps<TData> {
  children: (props: ChildrenProps<TData>) => JSX.Element;
  containerHeight: number;
  containerWidth: number;
  data: Node<TData>;
  node: {
    height: number;
    isDefaultExpanded?: (data: TData) => boolean;
    width: number;
  };
  treeLink: {
    getStroke?: (props: LinkProps<TData>) => string | undefined;
    getStrokeDasharray?: (
      props: LinkProps<TData>
    ) => string | number | undefined;
    getStrokeOpacity?: (props: LinkProps<TData>) => string | number | undefined;
    getStrokeWidth?: (props: LinkProps<TData>) => string | number | undefined;
  };
}
