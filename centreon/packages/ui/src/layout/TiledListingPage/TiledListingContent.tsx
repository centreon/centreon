import { ReactNode } from 'react';

import Responsive from '../../Responsive';

import { useTiledListingActionsStyles } from './TiledListingPage.styles';

interface TiledListingContentProps {
  children: ReactNode;
}

export const TiledListingContent = ({
  children
}: TiledListingContentProps): JSX.Element => {
  const { classes } = useTiledListingActionsStyles();

  return (
    <Responsive>
      <div className={classes.actions}>{children}</div>
    </Responsive>
  );
};
