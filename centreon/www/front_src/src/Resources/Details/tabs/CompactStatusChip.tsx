import { makeStyles } from 'tss-react/mui';

import { StatusChip } from '@centreon/ui';
import type { StatusChipProps } from '@centreon/ui';

const useStyles = makeStyles()((theme) => ({
  root: {
    fontSize: theme.typography.body2.fontSize,
    height: 18,
    lineHeight: theme.spacing(2)
  }
}));

const CompactStatusChip = (props: StatusChipProps): JSX.Element => {
  const { classes } = useStyles();

  return <StatusChip classes={{ root: classes.root }} {...props} />;
};

export default CompactStatusChip;
export { useStyles as useCompactStatusChipStyles };
