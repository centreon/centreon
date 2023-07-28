import { ReactElement, useCallback } from 'react';

import CloseIcon from '@mui/icons-material/Close';

import { IconButton } from '..';

import { useItemStyles } from './ItemComposition.styles';

type Props = {
  children: Array<ReactElement>;
  key: string | number;
  labelDelete: string;
  onDeleteItem: (key: string | number) => void;
};

export const Item = ({
  onDeleteItem,
  key,
  children,
  labelDelete
}: Props): JSX.Element => {
  const { classes } = useItemStyles();

  const deleteItem = useCallback(() => onDeleteItem(key), [key]);

  return (
    <div className={classes.itemContainer}>
      <div className={classes.itemContent}>{children}</div>
      <IconButton
        aria-label={labelDelete}
        data-testid={labelDelete}
        icon={<CloseIcon />}
        size="small"
        variant="ghost"
        onClick={deleteItem}
      />
    </div>
  );
};
