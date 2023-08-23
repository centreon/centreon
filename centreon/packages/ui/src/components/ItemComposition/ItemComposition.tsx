import { ReactElement } from 'react';

import AddIcon from '@mui/icons-material/Add';

import { Button } from '..';

import { useItemCompositionStyles } from './ItemComposition.styles';

type Props = {
  addButtonHidden?: boolean;
  addbuttonDisabled?: boolean;
  children: Array<ReactElement>;
  labelAdd: string;
  onAddItem: () => void;
};

export const ItemComposition = ({
  onAddItem,
  children,
  labelAdd,
  addbuttonDisabled,
  addButtonHidden
}: Props): JSX.Element => {
  const { classes } = useItemCompositionStyles();

  return (
    <div className={classes.itemCompositionContainer}>
      {children}
      {!addButtonHidden && (
        <Button
          aria-label={labelAdd}
          data-testid={labelAdd}
          disabled={addbuttonDisabled}
          icon={<AddIcon />}
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
