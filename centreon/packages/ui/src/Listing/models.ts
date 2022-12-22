interface EllipsisTypography {
  className?: string;
  formattedString: string;
}

export interface ComponentColumnProps {
  isHovered: boolean;
  isSelected: boolean;
  renderEllipsisTypography?: ({
    className,
    formattedString
  }: EllipsisTypography) => JSX.Element;
  row;
}

export interface Column {
  Component?: (props: ComponentColumnProps) => JSX.Element | null;
  clickable?: boolean;
  compact?: boolean;
  disablePadding?: boolean;
  disabled?: boolean;
  getColSpan?: (isSelected) => number | undefined;
  getFormattedString?: (row) => string | null;
  getHiddenCondition?: (isSelected) => boolean;
  getRenderComponentCondition?: (row) => boolean;
  getRenderComponentOnRowUpdateCondition?: (row) => boolean;
  hasHoverableComponent?: boolean;
  id: string;
  isTruncated?: boolean;
  label: string;
  rowMemoProps?: Array<string>;
  shortLabel?: string;
  sortField?: string;
  sortable?: boolean;
  type: ColumnType;
  width?: number | string;
}

export enum ColumnType {
  string = 0,
  component = 1
}

export interface RowColorCondition {
  color: string;
  condition: (row) => boolean;
  name: string;
}

export type RowId = number | string;

export interface ColumnConfiguration {
  selectedColumnIds?: Array<string>;
  sortable: boolean;
}

export type SortOrder = 'asc' | 'desc';

export interface PredefinedRowSelection {
  label: string;
  rowCondition: (row) => boolean;
}

export interface HeaderTable {
  backgroundColor: string;
  color: string;
  height: number;
}
