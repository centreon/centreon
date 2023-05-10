import {
  Card as MuiCard,
  CardActionArea as MuiCardActionArea,
  CardActions as MuiCardActions,
  CardContent as MuiCardContent
} from '@mui/material';
import React, { useMemo } from 'react';
import { useStyles } from './ListItem.styles';
import { IconButton } from '../../Button';
import { Delete as DeleteIcon, Edit as EditIcon } from '@mui/icons-material';


export type ListItemProps = {
  title: string;
  description: string;
  hasCardAction?: boolean;
  hasActions?: boolean;
  onClick?: (e: any) => void;
  onEdit?: (e: any) => void;
  onDelete?: (e: any) => void;
};

const ListItem: React.FC<ListItemProps> = ({
  title,
  description,
  hasCardAction = false,
  hasActions = false,
  onClick,
  onEdit,
  onDelete

}): JSX.Element => {
  const {classes} = useStyles();

  const ActionArea = useMemo(
    () => hasCardAction ? MuiCardActionArea : React.Fragment,
    [hasCardAction]);

  return (
    <MuiCard
      className={classes.listItem}
      variant="outlined"
    >
      <ActionArea
        onClick={e => onClick?.(e)}
      >
        <MuiCardContent>
          <h3>{title}</h3>
          <p>{description}</p>
        </MuiCardContent>
      </ActionArea>
      {hasActions && (
        <MuiCardActions>
          <IconButton
            icon={<EditIcon/>}
            variant="primary"
            size="small"
            onClick={e => onEdit?.(e)}
          />
          <IconButton
            icon={<DeleteIcon/>}
            variant="ghost"
            size="small"
            onClick={e => onDelete?.(e)}
          />
        </MuiCardActions>
      )}
    </MuiCard>
  );
};

export { ListItem };