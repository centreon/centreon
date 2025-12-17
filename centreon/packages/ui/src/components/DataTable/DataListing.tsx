import { Listing, type ListingProps } from "../..";
import type { RowId } from "../../Listing/models";

export const DataListing = <TRow extends { id: RowId }>(
	props: ListingProps<TRow>,
): JSX.Element => <Listing<TRow> {...props} />;
