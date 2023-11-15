import { Listing as TokenListing } from '@centreon/ui';

import { useColumns } from './useColumns';
import { useTokenListing } from './useTokenListing';

const Listing = (): JSX.Element | null => {
  const columns = useColumns();
  const { limitListing, pageListing, isLoading, rows, isError } =
    useTokenListing();

  if (isError) {
    return null;
  }

  return (
    <div style={{ height: 1000, margin: '54px 24px 0px 24px' }}>
      <TokenListing
        checkable
        columns={columns}
        limit={limitListing}
        loading={isLoading}
        page={pageListing}
        rows={rows}
      />
      ;
    </div>
  );
};
export default Listing;
