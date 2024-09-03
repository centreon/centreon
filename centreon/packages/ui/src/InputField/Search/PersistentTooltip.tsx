import { ReactElement, useState } from 'react';

import { T, cond, isNil } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import IconHelp from '@mui/icons-material/HelpOutline';
import IconClose from '@mui/icons-material/HighlightOff';
import { IconButton, Tooltip } from '@mui/material';

const useStyles = makeStyles()((theme) => ({
  buttonClose: {
    position: 'absolute',
    right: theme.spacing(-0.5),
    top: theme.spacing(-1)
  },
  tooltip: {
    backgroundColor: theme.palette.common.white,
    boxShadow: theme.shadows[3],
    color: theme.palette.text.primary,
    fontSize: theme.typography.pxToRem(12),
    maxWidth: 500
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
    <div style={{ position: 'relative' }}>
      <IconButton
        className={classes.buttonClose}
        size="small"
        onClick={closeTooltip}
      >
        <IconClose fontSize="small" />
      </IconButton>
      {children}
    </div>
  );

  return (
    <Tooltip
      classes={{ tooltip: classes.tooltip }}
      open={openTooltip || open}
      title={title}
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
