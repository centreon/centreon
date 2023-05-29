import { ReactNode } from 'react';

import { useTiledListPageStyles } from './TiledListingPage.styles';

interface TiledListingPageProps {
  children: Array<ReactNode>;
}

export const TiledListingPage = ({
  children
}: TiledListingPageProps): JSX.Element => {
  const { classes } = useTiledListPageStyles();

  return <div className={classes.listPage}>{children}</div>;
};
