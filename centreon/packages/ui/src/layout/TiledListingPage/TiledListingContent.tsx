import { ReactNode } from 'react';

import { useTiledListingActionsStyles } from './TiledListingPage.styles';

interface TiledListingContentProps {
  children: ReactNode;
}

export const TiledListingContent = ({
  children
}: TiledListingContentProps): JSX.Element => {
  const { classes } = useTiledListingActionsStyles();

  return <div className={classes.actions}>{children}</div>;
};
