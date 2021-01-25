import React from 'react';

import { makeStyles, Theme, Chip, fade, ChipProps } from '@material-ui/core';
import { CreateCSSProperties } from '@material-ui/styles';

enum SeverityCode {
  High = 1,
  Medium = 2,
  Low = 3,
  Pending = 4,
  Ok = 5,
  None = 6,
}

interface StatusColorProps {
  theme: Theme;
  severityCode: SeverityCode;
}

interface Colors {
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
      backgroundColor: palette.action.disabled,
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
      backgroundColor: fade(palette.primary.main, 0.1),
      color: palette.primary.main,
    },
  };

  return colorMapping[severityCode];
};

type Props = {
  label?: string;
  severityCode: SeverityCode;
  clickable?: boolean;
} & ChipProps;

const useStyles = makeStyles<Theme, Props>((theme) => ({
  chip: ({ severityCode, label }: Props): CreateCSSProperties<Props> => ({
    ...getStatusColors({ theme, severityCode }),
    ...(!label && {
      borderRadius: theme.spacing(1.5),
      width: theme.spacing(1.5),
      height: theme.spacing(1.5),
    }),
    '&:hover': { ...getStatusColors({ theme, severityCode }) },
  }),
}));

const StatusChip = ({
  severityCode,
  label,
  clickable = false,
  ...rest
}: Props): JSX.Element => {
  const classes = useStyles({ severityCode, label });

  return (
    <Chip
      size="small"
      clickable={clickable}
      label={label?.toUpperCase()}
      className={classes.chip}
      {...rest}
    />
  );
};

export { SeverityCode, getStatusColors };
export default StatusChip;
