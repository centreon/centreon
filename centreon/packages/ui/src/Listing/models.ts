interface EllipsisTypography {
  className?: string;
  formattedString: string;
}

export type RowRecord = Record<string, unknown>;

export interface ComponentColumnProps {
  isHovered: boolean;
  isSelected: boolean;
  renderEllipsisTypography?: ({
    className,
    formattedString
  }: EllipsisTypography) => JSX.Element;
  row: RowRecord;
}

export interface Column {
  Component?: (props: ComponentColumnProps) => JSX.Element | null;
  align?: 'start' | 'end' | 'center';
  clickable?: boolean;
  compact?: boolean;
  disablePadding?: boolean;
  disabled?: boolean;
  displaySubItemsCaret?: boolean;
  getColSpan?: (isSelected: boolean) => number | undefined;
  getFormattedString?: (row: RowRecord) => string | null;
  getHiddenCondition?: (isSelected: boolean) => boolean;
  getRenderComponentCondition?: (row: RowRecord) => boolean;
  getRenderComponentOnRowUpdateCondition?: (row: RowRecord) => boolean;
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
  condition: (row: RowRecord) => boolean;
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
  rowCondition: (row: RowRecord) => boolean;
}

export interface TableStyleAtom {
  body: {
    fontSize: string;
    height: number;
  };
  header: {
    height: number;
  };
  statusColumnChip: {
    height: number;
    width: number;
  };
}

export interface ListingSubItems {
  canCheckSubItems: boolean;
  enable: boolean;
  getRowProperty: (row?: RowRecord) => string;
  labelCollapse: string;
  labelExpand: string;
}
