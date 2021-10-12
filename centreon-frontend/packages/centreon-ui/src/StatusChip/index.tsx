import React from 'react';

import { makeStyles, Theme, Chip, alpha, ChipProps } from '@material-ui/core';
import { CreateCSSProperties } from '@material-ui/styles';
import { grey } from '@material-ui/core/colors';

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
  color: string;
}

const getStatusColors = ({ theme, severityCode }: StatusColorProps): Colors => {
  const { palette } = theme;

  const colorMapping = {
    [SeverityCode.High]: {
      backgroundColor: palette.error.main,
      color: palette.common.white,
    },
    [SeverityCode.Medium]: {
      backgroundColor: palette.warning.main,
      color: palette.common.black,
    },
    [SeverityCode.Low]: {
      backgroundColor: grey[500],
      color: palette.common.black,
    },
    [SeverityCode.Pending]: {
      backgroundColor: palette.info.main,
      color: palette.common.white,
    },
    [SeverityCode.Ok]: {
      backgroundColor: palette.success.main,
      color: palette.common.black,
    },
    [SeverityCode.None]: {
      backgroundColor: alpha(palette.primary.main, 0.1),
      color: palette.primary.main,
    },
  };

  return colorMapping[severityCode];
};

export type Props = {
  clickable?: boolean;
  label?: string;
  severityCode: SeverityCode;
} & ChipProps;

const useStyles = makeStyles<Theme, Props>((theme) => ({
  chip: ({ severityCode, label }: Props): CreateCSSProperties<Props> => ({
    ...getStatusColors({ severityCode, theme }),
    ...(!label && {
      borderRadius: theme.spacing(1.5),
      height: theme.spacing(1.5),
      width: theme.spacing(1.5),
    }),
    '&:hover': { ...getStatusColors({ severityCode, theme }) },
  }),
}));

const StatusChip = ({
  severityCode,
  label,
  clickable = false,
  ...rest
}: Props): JSX.Element => {
  const classes = useStyles({ label, severityCode });

  return (
    <Chip
      className={classes.chip}
      clickable={clickable}
      label={label?.toUpperCase()}
      size="small"
      {...rest}
    />
  );
};

export { SeverityCode, getStatusColors };
export default StatusChip;
