import React from 'react';

import clsx from 'clsx';
import numeral from 'numeral';

import { makeStyles, Theme, Avatar } from '@material-ui/core';
import { CreateCSSProperties } from '@material-ui/styles';

import { getStatusColors } from '@centreon/ui';

import { Colors, SeverityCode } from '../StatusChip';

interface StyleProps {
  severityCode: SeverityCode;
}

const useStyles = makeStyles<Theme, StyleProps>((theme) => {
  const getStatusIconColors = (severityCode: SeverityCode): Colors =>
    getStatusColors({
      severityCode,
      theme,
    });

  return {
    bordered: ({ severityCode }): CreateCSSProperties<StyleProps> => ({
      background: 'transparent',
      border: `2px solid ${getStatusIconColors(severityCode).backgroundColor}`,
    }),
    colored: ({ severityCode }): CreateCSSProperties<StyleProps> => ({
      background: getStatusIconColors(severityCode).backgroundColor,
      border: '2px solid transparent',
      color: getStatusIconColors(severityCode).color,
    }),

    icon: {
      cursor: 'pointer',
      fontSize: theme.typography.body1.fontSize,
      height: theme.spacing(3.5),
      width: theme.spacing(3.5),
    },
  };
});

interface Props {
  count: number | JSX.Element;
  severityCode: SeverityCode;
}

const StatusCounter = ({ severityCode, count }: Props): JSX.Element => {
  const classes = useStyles({ severityCode });

  const avatarClass = count > 0 ? classes.colored : classes.bordered;

  return (
    <Avatar className={clsx(avatarClass, classes.icon)}>
      {numeral(count).format('0a')}
    </Avatar>
  );
};

export default StatusCounter;
