export type TreeNode = {
  id: any;
  children?: Array<TreeNode>;
  name: string;
  group?: string;
  status?: string;
};
