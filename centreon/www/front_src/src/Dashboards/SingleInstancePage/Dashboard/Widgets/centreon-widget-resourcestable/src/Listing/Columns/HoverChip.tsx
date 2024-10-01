import { useEffect, useState } from 'react';

import { not } from 'ramda';

import { Tooltip } from '@mui/material';

import { useMemoComponent } from '@centreon/ui';

import { useHoverChiptStyles } from './Columns.styles';

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
  const { classes } = useHoverChiptStyles();

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
        PopperProps={{
          onClick: (e): void => {
            e.preventDefault();
            e.stopPropagation();
          }
        }}
        aria-label={label}
        classes={{ tooltip: classes.tooltip }}
        enterDelay={200}
        enterNextDelay={200}
        leaveDelay={0}
        open={isChipHovered}
        placement="left"
        title={<span>{children({ close: closeTooltip, isChipHovered })}</span>}
        onClick={(e): void => {
          e.preventDefault();
          e.stopPropagation();

          onClick?.();
        }}
        onClose={closeTooltip}
        onOpen={openTooltip}
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
