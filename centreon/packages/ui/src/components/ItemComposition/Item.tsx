import { ReactElement } from 'react';

import CloseIcon from '@mui/icons-material/Close';

import { IconButton } from '..';

import { useItemStyles } from './ItemComposition.styles';

type Props = {
  children: ReactElement | Array<ReactElement>;
  className?: string;
  deleteButtonHidden?: boolean;
  labelDelete?: string;
  onDeleteItem?: () => void;
};

export const Item = ({
  onDeleteItem,
  children,
  labelDelete,
  className,
  deleteButtonHidden
}: Props): JSX.Element => {
  const { classes, cx } = useItemStyles();

  return (
    <div className={classes.itemContainer}>
      <div className={cx(classes.itemContent, className)}>{children}</div>
      <div className={cx({ [classes.visibilityHiden]: deleteButtonHidden })}>
        <IconButton
          aria-label={labelDelete}
          data-testid={labelDelete}
          icon={<CloseIcon />}
          size="small"
          variant="ghost"
          onClick={onDeleteItem}
        />
      </div>
    </div>
  );
};
