import { ReactElement } from 'react';

import { gt } from 'ramda';

import AddIcon from '@mui/icons-material/Add';
import LinkIcon from '@mui/icons-material/Link';
import { Typography } from '@mui/material';

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
};

export const ItemComposition = ({
  onAddItem,
  children,
  labelAdd,
  addbuttonDisabled,
  addButtonHidden,
  IconAdd,
  displayItemsAsLinked,
  secondaryLabel
}: Props): JSX.Element => {
  const { classes } = useItemCompositionStyles();

  const hasMoreThanOneChildren = gt(children.length, 1);

  return (
    <div className={classes.itemCompositionContainer}>
      <div className={classes.itemCompositionItemsAndLink}>
        <div className={classes.itemCompositionItems}>{children}</div>
        {displayItemsAsLinked && hasMoreThanOneChildren && (
          <div data-linked className={classes.linkedItems}>
            <LinkIcon className={classes.linkIcon} viewBox="0 0 24 24" />
          </div>
        )}
      </div>
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
