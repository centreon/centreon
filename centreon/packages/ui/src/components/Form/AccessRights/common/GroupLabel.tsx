import { ReactElement } from 'react';

import { useStyles } from './GroupLabel.styles';

export type GroupLabelProps = {
  label: string;
};

const GroupLabel = ({ label }: GroupLabelProps): ReactElement => {
  const { classes } = useStyles();

  return <span className={classes.groupLabel}>{label}</span>;
};

export { GroupLabel };
