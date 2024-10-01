import { Typography } from '@mui/material';

import { SeverityCode, StatusChip } from '@centreon/ui';

import { useTypeChipStyles } from './Columns.styles';

interface Props {
  label: string;
}

const ShortTypeChip = ({ label }: Props): JSX.Element => {
  const { classes } = useTypeChipStyles();

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
