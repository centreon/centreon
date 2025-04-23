import { Listing, ListingProps } from '../..';
import { RowId } from '../../Listing/models';

export const DataListing = <TRow extends { id: RowId }>(
  props: ListingProps<TRow>
): JSX.Element => <Listing<TRow> {...props} />;
