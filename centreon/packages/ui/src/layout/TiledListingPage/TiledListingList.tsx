import { ReactNode } from 'react';

interface TiledListingListProps {
  children: Array<ReactNode>;
}

export const TiledListingList = ({
  children
}: TiledListingListProps): JSX.Element => {
  return <div>{children}</div>;
};
