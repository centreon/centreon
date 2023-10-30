import { ReactElement, ReactNode } from 'react';

import { useStyles } from './PageLayout.styles';

type PageLayoutProps = {
  children: Array<ReactNode> | ReactNode;
  variant?: 'default' | 'fixed-header';
};

export const PageLayout = ({
  children,
  variant = 'default'
}: PageLayoutProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <section className={classes.pageLayout} data-variant={variant}>
      {children}
    </section>
  );
};
