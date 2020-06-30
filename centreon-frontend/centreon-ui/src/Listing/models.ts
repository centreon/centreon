export interface ComponentColumnProps {
  isHovered: boolean;
  isSelected: boolean;
  row;
}

export interface Column {
  id: string;
  label: string;
  type: ColumnType;
  Component?: (props: ComponentColumnProps) => JSX.Element;
  clickable?: boolean;
  width?: number | string;
  getFormattedString?: (row) => string | null;
  getColSpan?: (isSelected) => number | undefined;
  getTruncationState?: (isSelected) => boolean;
  disablePadding?: boolean;
  sortable?: boolean;
}

enum ColumnType {
  string = 0,
  component = 1,
}

export interface RowColorCondition {
  name: string;
  condition: (row) => boolean;
  color: string;
}

export { ColumnType };
