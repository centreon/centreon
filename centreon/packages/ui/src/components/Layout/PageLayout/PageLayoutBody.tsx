import { ReactElement, ReactNode } from 'react';

import { useStyles } from './PageLayout.styles';

type PageLayoutBodyProps = {
  children: Array<ReactNode> | ReactNode;
  hasBackground?: boolean;
};

export const PageLayoutBody = ({
  children,
  hasBackground = false
}: PageLayoutBodyProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <section
      className={classes.pageLayoutBody}
      data-has-background={hasBackground}
    >
      {children}
    </section>
  );
};
