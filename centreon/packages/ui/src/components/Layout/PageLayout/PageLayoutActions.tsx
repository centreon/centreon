import { ReactElement, ReactNode } from 'react';

import { useStyles } from './PageLayout.styles';

type PageLayoutActionsProps = {
  children: Array<ReactNode> | ReactNode;
}

export const PageLayoutActions = ({
  children
}: PageLayoutActionsProps): ReactElement => {
  const { classes } = useStyles();

  return <section className={classes.pageLayoutActions}>{children}</section>;
};
