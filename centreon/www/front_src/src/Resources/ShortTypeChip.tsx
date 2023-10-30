import { makeStyles } from 'tss-react/mui';

import { Typography } from '@mui/material';

import { SeverityCode, StatusChip } from '@centreon/ui';

const useStyles = makeStyles()((theme) => ({
  containerLabel: {
    padding: theme.spacing(0.5)
  },
  label: {
    alignItems: 'center',
    justifyContent: 'center',
    lineHeight: 1
  }
}));

interface Props {
  label: string;
}

const ShortTypeChip = ({ label }: Props): JSX.Element => {
  const { classes } = useStyles();

  return (
    <StatusChip
      classes={{
        label: classes.containerLabel
      }}
      label={<Typography className={classes.label}>{label}</Typography>}
      severityCode={SeverityCode.None}
    />
  );
};

export default ShortTypeChip;
