import { ReactElement } from 'react';

import { gt } from 'ramda';

import AddIcon from '@mui/icons-material/Add';
import LinkIcon from '@mui/icons-material/Link';

import { Button } from '..';

import { useItemCompositionStyles } from './ItemComposition.styles';

type Props = {
  IconAdd?;
  addButtonHidden?: boolean;
  addbuttonDisabled?: boolean;
  children: Array<ReactElement>;
  displayItemsAsLinked?: boolean;
  labelAdd: string;
  onAddItem: () => void;
};

export const ItemComposition = ({
  onAddItem,
  children,
  labelAdd,
  addbuttonDisabled,
  addButtonHidden,
  IconAdd,
  displayItemsAsLinked
}: Props): JSX.Element => {
  const { classes } = useItemCompositionStyles();

  const hasMoreThanOneChildren = gt(children.length, 1);

  return (
    <div className={classes.itemCompositionContainer}>
      <div className={classes.itemCompositionItemsAndLink}>
        <div className={classes.itemCompositionItems}>{children}</div>
        {displayItemsAsLinked && hasMoreThanOneChildren && (
          <div className={classes.linkedItems}>
            <LinkIcon className={classes.linkIcon} viewBox="0 0 24 24" />
          </div>
        )}
      </div>
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
    </div>
  );
};
