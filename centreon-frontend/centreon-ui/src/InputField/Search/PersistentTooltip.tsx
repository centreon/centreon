import * as React from 'react';

import { isNil, cond, T } from 'ramda';

import { Tooltip, makeStyles, IconButton } from '@material-ui/core';
import IconHelp from '@material-ui/icons/HelpOutline';
import IconClose from '@material-ui/icons/HighlightOff';

const useStyles = makeStyles((theme) => ({
  tooltip: {
    backgroundColor: theme.palette.common.white,
    color: theme.palette.text.primary,
    boxShadow: theme.shadows[3],
    fontSize: theme.typography.pxToRem(12),
    maxWidth: 500,
    padding: theme.spacing(1, 2, 1, 1),
  },
  buttonClose: {
    position: 'absolute',
    right: theme.spacing(0.5),
  },
}));

interface Props {
  children?: React.ReactElement;
  labelSearchHelp: string;
  openTooltip?: boolean;
  toggleTooltip?: () => void;
  closeTooltip?: () => void;
}

const PersistentTooltip = ({
  children,
  labelSearchHelp,
  openTooltip,
  toggleTooltip: toggleTooltipProp,
  closeTooltip: closeTooltipProp,
}: Props): JSX.Element => {
  const classes = useStyles();

  const [open, setOpen] = React.useState(openTooltip || false);

  const toggleTooltip = (): void => {
    cond([
      [isNil, (): void => setOpen(!open)],
      [T, (): void => toggleTooltipProp?.()],
    ])(openTooltip);
  };

  const closeTooltip = (): void => {
    if (!openTooltip) {
      setOpen(false);
      return;
    }

    closeTooltipProp?.();
  };

  const title = (
    <>
      <IconButton
        size="small"
        onClick={closeTooltip}
        className={classes.buttonClose}
      >
        <IconClose fontSize="small" />
      </IconButton>
      {children}
    </>
  );

  return (
    <Tooltip
      open={open}
      title={title}
      classes={{ tooltip: classes.tooltip }}
      interactive
    >
      <IconButton
        aria-label={labelSearchHelp}
        size="small"
        onClick={toggleTooltip}
      >
        <IconHelp />
      </IconButton>
    </Tooltip>
  );
};

export default PersistentTooltip;
