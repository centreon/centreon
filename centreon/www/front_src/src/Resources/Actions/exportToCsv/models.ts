import { labelAllColumns, labelAllPages, labelCurrentPageOnly, labelVisibleColumnsOnly } from '../../translatedLabels';

export interface ListSearch {
  array: Array<string>;
  field: string;
}

export interface Label {
  firstLabel: string;
  secondLabel: string;
}

export interface CheckedLabel {
  label: string;
  isChecked: boolean;
}

export type CheckedValue = {
  defaultLabel: string;
} & CheckedLabel;

export interface Count {
  count: number;
  meta: {
    total: number;
    search: object;
  };
}


export enum ColumnId {
  visibleColumns = 'visibleColumns',
  allColumns = 'allColumns'
}

export enum PageId {
  currentPage = 'currentPage',
  allPages = 'allPages'
}

export const columnOptions = [
  { id: ColumnId.visibleColumns, name: labelVisibleColumnsOnly },
  { id: ColumnId.allColumns, name: labelAllColumns }
];

export const pageOptions = [
  { id: PageId.currentPage, name: labelCurrentPageOnly },
  { id: PageId.allPages, name: labelAllPages }
];
