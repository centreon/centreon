import { ReactElement, ReactNode } from 'react';

import { useStyles } from './PageLayout.styles';

interface PageLayoutActionsProps {
  children: Array<ReactNode> | ReactNode;
  rowReverse?: boolean;
}

export const PageLayoutActions = ({
  children,
  rowReverse
}: PageLayoutActionsProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <section
      className={classes.pageLayoutActions}
      data-row-reverse={rowReverse}
      id="actions"
    >
      {children}
    </section>
  );
};
