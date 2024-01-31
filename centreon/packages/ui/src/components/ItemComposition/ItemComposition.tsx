import { ReactElement } from 'react';

import AddIcon from '@mui/icons-material/Add';
import { Typography } from '@mui/material';

import { Button } from '..';

import { useItemCompositionStyles } from './ItemComposition.styles';

export type Props = {
  IconAdd?;
  addButtonHidden?: boolean;
  addbuttonDisabled?: boolean;
  children: Array<ReactElement>;
  labelAdd: string;
  onAddItem: () => void;
  secondaryLabel?: string;
};

export const ItemComposition = ({
  onAddItem,
  children,
  labelAdd,
  addbuttonDisabled,
  addButtonHidden,
  IconAdd,
  secondaryLabel
}: Props): JSX.Element => {
  const { classes } = useItemCompositionStyles();

  return (
    <div className={classes.itemCompositionContainer}>
      {children}
      <div className={classes.buttonAndSecondaryLabel}>
        {!addButtonHidden && (
          <Button
            aria-label={labelAdd}
            data-testid={labelAdd}
            disabled={addbuttonDisabled}
            icon={IconAdd || <AddIcon />}
            iconVariant="start"
            size="small"
            variant="ghost"
            onClick={onAddItem}
          >
            {labelAdd}
          </Button>
        )}
        {secondaryLabel && (
          <Typography sx={{ color: 'text.secondary' }}>
            {secondaryLabel}
          </Typography>
        )}
      </div>
    </div>
  );
};
