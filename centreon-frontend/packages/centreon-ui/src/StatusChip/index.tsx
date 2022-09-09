import { makeStyles } from 'tss-react/mui';
import { equals } from 'ramda';

import { Theme, Chip, ChipProps, alpha } from '@mui/material';
import { grey } from '@mui/material/colors';

import { ThemeMode } from '@centreon/ui-context';

enum SeverityCode {
  High = 1,
  Medium = 2,
  Low = 3,
  Pending = 4,
  Ok = 5,
  None = 6,
}

interface StatusColorProps {
  severityCode: SeverityCode;
  theme: Theme;
}

export interface Colors {
  backgroundColor: string;
}

const getStatusColors = ({ theme, severityCode }: StatusColorProps): Colors => {
  const { palette } = theme;

  const colorMapping = {
    [SeverityCode.High]: {
      backgroundColor: palette.error.main,
    },
    [SeverityCode.Medium]: {
      backgroundColor: palette.warning.main,
    },
    [SeverityCode.Low]: {
      backgroundColor:
        grey[equals(ThemeMode.dark, theme.palette.mode) ? 600 : 300],
    },
    [SeverityCode.Pending]: {
      backgroundColor: palette.pending.main,
    },
    [SeverityCode.Ok]: {
      backgroundColor: palette.success.main,
    },
    [SeverityCode.None]: {
      backgroundColor: alpha(palette.primary.main, 0.1),
    },
  };

  return colorMapping[severityCode];
};

export type Props = {
  clickable?: boolean;
  label?: string;
  severityCode: SeverityCode;
} & ChipProps;

const useStyles = makeStyles<Props>()((theme, { severityCode, label }) => ({
  chip: {
    ...getStatusColors({ severityCode, theme }),
    ...(!label && {
      borderRadius: theme.spacing(1.5),
      height: theme.spacing(1.5),
      width: theme.spacing(1.5),
    }),
    '&:hover': { ...getStatusColors({ severityCode, theme }) },
  },
}));

const StatusChip = ({
  severityCode,
  label,
  clickable = false,
  ...rest
}: Props): JSX.Element => {
  const { classes } = useStyles({ label, severityCode });

  return (
    <Chip
      className={classes.chip}
      clickable={clickable}
      label={label}
      size="small"
      {...rest}
    />
  );
};

export { SeverityCode, getStatusColors };
export default StatusChip;
