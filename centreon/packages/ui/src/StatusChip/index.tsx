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
  None = 6
}

interface StatusColorProps {
  className?: string;
  severityCode: SeverityCode;
  theme: Theme;
}

export interface Colors {
  backgroundColor: string;
  color: string;
}

const getStatusColors = ({ theme, severityCode }: StatusColorProps): Colors => {
  const { palette } = theme;

  const colorMapping = {
    [SeverityCode.High]: {
      backgroundColor: palette.error.main,
      color: palette.error.contrastText
    },
    [SeverityCode.Medium]: {
      backgroundColor: palette.warning.main,
      color: palette.warning.contrastText
    },
    [SeverityCode.Low]: {
      backgroundColor:
        grey[equals(ThemeMode.dark, theme.palette.mode) ? 600 : 300],
      color: '#000'
    },
    [SeverityCode.Pending]: {
      backgroundColor: palette.pending.main,
      color: '#fff'
    },
    [SeverityCode.Ok]: {
      backgroundColor: palette.success.main,
      color: palette.success.contrastText
    },
    [SeverityCode.None]: {
      backgroundColor: alpha(palette.primary.main, 0.1),
      color: palette.text.primary
    }
  };

  return colorMapping[severityCode];
};

export type Props = {
  clickable?: boolean;
  label?: string;
  severityCode: SeverityCode;
} & ChipProps;

const useStyles = makeStyles<Props>()((theme, { severityCode }) => ({
  chip: {
    '&:hover': { ...getStatusColors({ severityCode, theme }) },
    ...getStatusColors({ severityCode, theme })
  }
}));

const StatusChip = ({
  severityCode,
  label,
  clickable = false,
  className,
  ...rest
}: Props): JSX.Element => {
  const { classes, cx } = useStyles({ label, severityCode });

  const lowerLabel = (name: string): string =>
    name.charAt(0).toUpperCase() + name.slice(1).toLowerCase();

  return (
    <Chip
      className={cx(classes.chip, className)}
      clickable={clickable}
      label={
        equals(typeof label, 'string') ? lowerLabel(label as string) : label
      }
      {...rest}
    />
  );
};

export { SeverityCode, getStatusColors };
export default StatusChip;
