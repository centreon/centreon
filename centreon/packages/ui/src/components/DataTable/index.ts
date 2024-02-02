import { DataTable as DataTableRoot } from './DataTable';
import { DataTableItem } from './Item/DataTableItem';
import { DataTableItemSkeleton } from './Item/DataTableItemSkeleton';
import { DataTableEmptyState } from './EmptyState/DataTableEmptyState';
import { DataListing } from './DataListing';

export const DataTable = Object.assign(DataTableRoot, {
  EmptyState: DataTableEmptyState,
  Item: DataTableItem,
  ItemSkeleton: DataTableItemSkeleton,
  Listing: DataListing
});
