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

export type Link = 'curve' | 'line' | 'step';

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
  onMouseDown: (e) => void;
  onMouseUp: (callback) => (e) => void;
}

export interface TreeProps<TData> {
  changeTree?: (newTree: Node<TData>) => void;
  children: (props: ChildrenProps<TData>) => JSX.Element;
  containerHeight: number;
  containerWidth: number;
  contentHeight?: number;
  contentWidth?: number;
  node: {
    height: number;
    isDefaultExpanded?: (data: TData) => boolean;
    width: number;
  };
  tree: Node<TData>;
  treeLink?: {
    getStroke?: (props: LinkProps<TData>) => string | undefined;
    getStrokeDasharray?: (
      props: LinkProps<TData>
    ) => string | number | undefined;
    getStrokeOpacity?: (props: LinkProps<TData>) => string | number | undefined;
    getStrokeWidth?: (props: LinkProps<TData>) => string | number | undefined;
    type?: Link;
  };
}
