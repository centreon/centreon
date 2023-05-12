import { ReactNode } from 'react';

import { useTiledListingActionsStyles } from './TiledListingPage.styles';

interface TiledListingActionsProps {
  children: Array<ReactNode>;
}

export const TiledListingActions = ({
  children
}: TiledListingActionsProps): JSX.Element => {
  const { classes } = useTiledListingActionsStyles();

  return <div className={classes.actions}>{children}</div>;
};
