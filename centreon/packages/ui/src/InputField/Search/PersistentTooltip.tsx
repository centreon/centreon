import { ReactElement, useState } from 'react';

import { isNil, cond, T } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Tooltip, IconButton } from '@mui/material';
import IconHelp from '@mui/icons-material/HelpOutline';
import IconClose from '@mui/icons-material/HighlightOff';

const useStyles = makeStyles()((theme) => ({
  buttonClose: {
    position: 'absolute',
    right: theme.spacing(0.5)
  },
  tooltip: {
    backgroundColor: theme.palette.common.white,
    boxShadow: theme.shadows[3],
    color: theme.palette.text.primary,
    fontSize: theme.typography.pxToRem(12),
    maxWidth: 500,
    padding: theme.spacing(1, 2, 1, 1)
  }
}));

interface Props {
  children?: ReactElement;
  closeTooltip?: () => void;
  labelSearchHelp: string;
  openTooltip?: boolean;
  toggleTooltip?: () => void;
}

const PersistentTooltip = ({
  children,
  labelSearchHelp,
  openTooltip,
  toggleTooltip: toggleTooltipProp,
  closeTooltip: closeTooltipProp
}: Props): JSX.Element => {
  const { classes } = useStyles();

  const [open, setOpen] = useState(openTooltip || false);

  const toggleTooltip = (): void => {
    cond([
      [isNil, (): void => setOpen(!open)],
      [T, (): void => toggleTooltipProp?.()]
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
        className={classes.buttonClose}
        size="small"
        onClick={closeTooltip}
      >
        <IconClose fontSize="small" />
      </IconButton>
      {children}
    </>
  );

  return (
    <Tooltip classes={{ tooltip: classes.tooltip }} open={open} title={title}>
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
