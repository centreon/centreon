import { Tooltip } from '@mui/material';

import { useMemoComponent } from '@centreon/ui';

import { not } from 'ramda';
import { useEffect, useState } from 'react';
import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()(() => ({
  iconButton: {
    padding: 0
  },
  tooltip: {
    backgroundColor: 'transparent',
    maxWidth: 'none'
  }
}));

interface Props {
  Chip: () => JSX.Element;
  children: (props?) => JSX.Element;
  isHovered?: boolean;
  label: string;
  onClick?: () => void;
}

const HoverChip = ({
  children,
  Chip,
  label,
  onClick,
  isHovered = false
}: Props): JSX.Element => {
  const { classes } = useStyles();

  const [isChipHovered, setIsChipHovered] = useState<boolean>(false);

  const openTooltip = (): void => setIsChipHovered(true);

  const closeTooltip = (): void => setIsChipHovered(false);

  useEffect(() => {
    if (not(isHovered)) {
      return;
    }
    setIsChipHovered(false);
  }, [isHovered]);

  return useMemoComponent({
    Component: (
      <Tooltip
        aria-label={label}
        classes={{ tooltip: classes.tooltip }}
        enterDelay={200}
        enterNextDelay={200}
        leaveDelay={0}
        onClick={(e): void => {
          e.preventDefault();
          e.stopPropagation();

          onClick?.();
        }}
        onClose={closeTooltip}
        onOpen={openTooltip}
        open={isChipHovered}
        PopperProps={{
          onClick: (e): void => {
            e.preventDefault();
            e.stopPropagation();
          }
        }}
        placement="left"
        title={<span>{children({ close: closeTooltip, isChipHovered })}</span>}
      >
        <span>
          <Chip />
        </span>
      </Tooltip>
    ),
    memoProps: [isHovered, isChipHovered, label]
  });
};

export default HoverChip;
