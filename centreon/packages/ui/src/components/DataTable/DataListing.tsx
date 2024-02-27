import { RowId } from '../../Listing/Listing/models';
import { Listing, ListingProps } from '../..';

export const DataListing = <TRow extends { id: RowId }>(
  props: ListingProps<TRow>
): JSX.Element => <Listing<TRow> {...props} />;
