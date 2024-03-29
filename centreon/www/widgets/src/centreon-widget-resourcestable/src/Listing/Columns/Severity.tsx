import { ReactNode } from 'react';

import { isNil } from 'ramda';

import Tooltip, { TooltipProps } from '@mui/material/Tooltip';

import type { ComponentColumnProps } from '@centreon/ui';

import { Severity } from '../models';

import { useSeverityStyles } from './Columns.styles';

interface Props {
  children: ReactNode;
  className?: string;
  title: TooltipProps['title'];
}

const WrapperTooltip = ({ title, children, className }: Props): JSX.Element => {
  return (
    <Tooltip className={className} title={title}>
      <div>{children}</div>
    </Tooltip>
  );
};

const Title = ({ severity }: { severity: Severity }): JSX.Element => {
  const { classes } = useSeverityStyles();

  return (
    <div className={classes.root}>
      <div className={classes.rowContainer}>
        <div className={classes.firstColumn}>name:</div>
        <div className={classes.text}>{severity.name}</div>
      </div>

      <div className={classes.rowContainer}>
        <div className={classes.firstColumn}>level:</div>
        <div className={classes.text}>{severity.level}</div>
      </div>
    </div>
  );
};

const SeverityColumn = ({ row }: ComponentColumnProps): JSX.Element | null => {
  const { classes } = useSeverityStyles();
  const isSeverityIcon = !isNil(row?.severity?.icon?.url);

  if (!row?.severity) {
    return null;
  }

  return (
    <div>
      {isSeverityIcon && (
        <WrapperTooltip
          className={classes.container}
          title={<Title severity={row?.severity} />}
        >
          <img
            alt="severity"
            height={24}
            src={row.severity?.icon?.url}
            width={24}
          />
        </WrapperTooltip>
      )}
    </div>
  );
};

export default SeverityColumn;
