import { RowId } from "../../Listing/models";
import { Listing, ListingProps } from "../..";

export const DataListing = <TRow extends {id: RowId}>(props: ListingProps<TRow>) => <Listing<TRow> {...props} />