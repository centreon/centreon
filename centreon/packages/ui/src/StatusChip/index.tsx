import { ReactNode } from 'react';

import { equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Chip, ChipProps, Theme } from '@mui/material';

import type { TableStyleAtom } from '../Listing/models';
import useStyleTable from '../Listing/useStyleTable';

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
      backgroundColor: theme.palette.statusChip.error,
      color: palette.error.contrastText
    },
    [SeverityCode.Medium]: {
      backgroundColor: theme.palette.statusChip.warning,
      color: palette.warning.contrastText
    },
    [SeverityCode.Low]: {
      backgroundColor: theme.palette.statusChip.unknown,
      color: palette.text.primary
    },
    [SeverityCode.Pending]: {
      backgroundColor: theme.palette.statusChip.pending,
      color: palette.text.primary
    },
    [SeverityCode.Ok]: {
      backgroundColor: theme.palette.statusChip.success,
      color: palette.text.primary
    },
    [SeverityCode.None]: {
      backgroundColor: theme.palette.statusChip.none,
      color: palette.text.primary
    }
  };

  return colorMapping[severityCode];
};

export type Props = {
  clickable?: boolean;
  label?: string | ReactNode;
  severityCode: SeverityCode;
  statusColumn?: boolean;
} & ChipProps;

interface StylesProps {
  data: TableStyleAtom['statusColumnChip'];
  severityCode: SeverityCode;
}

const useStyles = makeStyles<StylesProps>()(
  (theme, { severityCode, data }) => ({
    chip: {
      '&:hover': { ...getStatusColors({ severityCode, theme }) },
      ...getStatusColors({ severityCode, theme }),
      '& .MuiChip-label': {
        alignItems: 'center',
        display: 'flex',
        height: '100%',
        padding: 0
      }
    },
    statusColumnContainer: {
      fontWeight: 'bold',
      height: data.height,
      marginLeft: 1,
      minWidth: theme.spacing((data.width - 1) / 8)
    }
  })
);

const StatusChip = ({
  severityCode,
  label,
  clickable = false,
  statusColumn = false,
  className,
  ...rest
}: Props): JSX.Element => {
  const { dataStyle } = useStyleTable({});
  const { classes, cx } = useStyles({
    data: dataStyle.statusColumnChip,
    severityCode
  });

  const lowerLabel = (name: string): string =>
    name.charAt(0).toUpperCase() + name.slice(1).toLowerCase();

  return (
    <Chip
      className={cx(classes.chip, className, {
        [classes.statusColumnContainer]: statusColumn
      })}
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
