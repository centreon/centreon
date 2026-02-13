import AddIcon from '@mui/icons-material/Add';
import LinkIcon from '@mui/icons-material/Link';
import { Typography } from '@mui/material';

import { gt } from 'ramda';
import type { ReactElement } from 'react';

import { Button } from '..';
import { useItemCompositionStyles } from './ItemComposition.styles';

export type Props = {
  IconAdd?;
  addButtonHidden?: boolean;
  addbuttonDisabled?: boolean;
  children: Array<ReactElement>;
  displayItemsAsLinked?: boolean;
  labelAdd?: string;
  onAddItem?: () => void;
  secondaryLabel?: string;
  isAddButtonSticky?: boolean;
  addButtonClassName?: string;
};

export const ItemComposition = ({
  onAddItem,
  children,
  labelAdd,
  addbuttonDisabled,
  addButtonHidden,
  IconAdd,
  displayItemsAsLinked,
  secondaryLabel,
  isAddButtonSticky,
  addButtonClassName
}: Props): ReactElement => {
  const { classes } = useItemCompositionStyles();

  const hasMoreThanOneChildren = gt(children.length, 1);

  return (
    <div className={classes.itemCompositionContainer}>
      <div className={classes.itemCompositionItemsAndLink}>
        <div className={classes.itemCompositionItems}>{children}</div>
        {displayItemsAsLinked && hasMoreThanOneChildren && (
          <div className={classes.linkedItems} data-linked>
            <LinkIcon className={classes.linkIcon} viewBox="0 0 24 24" />
          </div>
        )}
      </div>
      <div
        className={`flex justify-between items-center w-full ${isAddButtonSticky && 'bg-background-paper sticky bottom-0 z-2'} ${addButtonClassName}`}
      >
        {!addButtonHidden && (
          <Button
            aria-label={labelAdd}
            data-testid={labelAdd}
            disabled={addbuttonDisabled}
            icon={IconAdd || <AddIcon />}
            iconVariant="start"
            onClick={onAddItem}
            size="small"
            variant="ghost"
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
