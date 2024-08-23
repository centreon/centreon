import { DataListing } from './DataListing';
import { DataTable as DataTableRoot } from './DataTable';
import { DataTableEmptyState } from './EmptyState/DataTableEmptyState';
import { DataTableItem } from './Item/DataTableItem';
import { DataTableItemSkeleton } from './Item/DataTableItemSkeleton';

export const DataTable = Object.assign(DataTableRoot, {
  EmptyState: DataTableEmptyState,
  Item: DataTableItem,
  ItemSkeleton: DataTableItemSkeleton,
  Listing: DataListing
});
