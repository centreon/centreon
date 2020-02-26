import React from 'react';

import { makeStyles, Theme, Chip, fade } from '@material-ui/core';
import { CreateCSSProperties } from '@material-ui/styles';

enum StatusCode {
  UpOrOk = 0,
  DownOrWarning = 1,
  UnreachableOrCritical = 2,
  Unknown = 3,
  Pending = 4,
  None = 5,
}

interface StatusColorProps {
  theme: Theme;
  statusCode: StatusCode;
}

interface Colors {
  backgroundColor: string;
  color: string;
}

const getStatusColors = ({ theme, statusCode }: StatusColorProps): Colors => {
  const { palette } = theme;

  const colorMapping = {
    [StatusCode.UpOrOk]: {
      backgroundColor: palette.success.main,
      color: palette.common.black,
    },
    [StatusCode.DownOrWarning]: {
      backgroundColor: palette.warning.main,
      color: palette.common.black,
    },
    [StatusCode.UnreachableOrCritical]: {
      backgroundColor: palette.error.main,
      color: palette.common.white,
    },
    [StatusCode.Unknown]: {
      backgroundColor: palette.action.disabled,
      color: palette.common.black,
    },
    [StatusCode.Pending]: {
      backgroundColor: palette.info.main,
      color: palette.common.white,
    },
    [StatusCode.None]: {
      backgroundColor: fade(palette.primary.main, 0.1),
      color: palette.primary.main,
    },
  };

  return colorMapping[statusCode];
};

interface Props {
  label?: string;
  statusCode: StatusCode;
  clickable?: boolean;
}

const useStyles = makeStyles<Theme, Props>((theme) => ({
  chip: ({ statusCode, label }: Props): CreateCSSProperties<Props> => ({
    ...getStatusColors({ theme, statusCode }),
    ...(!label && {
      borderRadius: theme.spacing(1.5),
      height: theme.spacing(1.5),
      width: theme.spacing(1.5),
    }),
    '&:hover': { ...getStatusColors({ theme, statusCode }) },
  }),
}));

const StatusChip = ({
  statusCode,
  label,
  clickable = true,
  ...rest
}: Props): JSX.Element => {
  const classes = useStyles({ statusCode, label });

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

export { StatusCode };
export default StatusChip;
