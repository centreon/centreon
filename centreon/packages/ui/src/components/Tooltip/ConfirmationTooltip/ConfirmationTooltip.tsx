import { MouseEvent, useState } from 'react';

import { ClickAwayListener, Box, Popper } from '@mui/material';

import TooltipContent from './TooltipContent';
import { Props } from './models';
import { useStyles } from './ConfirmationTooltip.styles';

export const ConfirmationTooltip = ({
  children,
  labels,
  onConfirm,
  confirmVariant
}: Props): JSX.Element => {
  const { classes } = useStyles();

  const [anchorElement, setAnchorElement] = useState<
    HTMLButtonElement | undefined
  >(undefined);

  const close = (): void => {
    setAnchorElement(undefined);
  };

  const open = (event: MouseEvent<HTMLButtonElement>): void => {
    setAnchorElement(event.currentTarget);
  };

  const confirm = (): void => {
    close();
    onConfirm();
  };

  const actions = [
    {
      action: close,
      label: labels.cancel
    },
    {
      action: confirm,
      label: labels.confirm.label,
      secondaryLabel: labels.confirm.secondaryLabel,
      variant: confirmVariant
    }
  ];

  const isOpen = Boolean(anchorElement);

  return (
    <ClickAwayListener onClickAway={close}>
      <div>
        {children({ isOpen, toggleTooltip: isOpen ? close : open })}
        <Popper
          anchorEl={anchorElement}
          className={classes.popper}
          open={isOpen}
          popperOptions={{
            placement: 'bottom-start'
          }}
        >
          <Box className={classes.paper}>
            <TooltipContent actions={actions} />
          </Box>
        </Popper>
      </div>
    </ClickAwayListener>
  );
};
