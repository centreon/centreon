export interface Breadcrumb {
  index?: number;
  is_react?: boolean;
  label: string;
  link: string;
  options?: string;
}

export interface BreadcrumbsByPath {
  [path: string]: Array<Breadcrumb>;
}
