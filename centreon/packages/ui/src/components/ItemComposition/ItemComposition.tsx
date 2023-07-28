import { ReactElement } from 'react';

import AddIcon from '@mui/icons-material/Add';

import { Button } from '..';

import { useItemCompositionStyles } from './ItemComposition.styles';

type Props = {
  children: Array<ReactElement>;
  labelAdd: string;
  onAddItem: () => void;
};

export const ItemComposition = ({
  onAddItem,
  children,
  labelAdd
}: Props): JSX.Element => {
  const { classes } = useItemCompositionStyles();

  return (
    <div className={classes.itemCompositionContainer}>
      {children}
      <Button
        aria-label={labelAdd}
        data-testid={labelAdd}
        icon={<AddIcon />}
        iconVariant="start"
        size="small"
        variant="ghost"
        onClick={onAddItem}
      >
        {labelAdd}
      </Button>
    </div>
  );
};
