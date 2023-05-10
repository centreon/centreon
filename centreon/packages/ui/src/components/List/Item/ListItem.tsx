import React, { useMemo } from 'react';

import {
  Card as MuiCard,
  CardActionArea as MuiCardActionArea,
  CardActions as MuiCardActions,
  CardContent as MuiCardContent
} from '@mui/material';
import { Delete as DeleteIcon, Edit as EditIcon } from '@mui/icons-material';

import { IconButton } from '../../Button';

import { useStyles } from './ListItem.styles';

export interface ListItemProps {
  description: string;
  hasActions?: boolean;
  hasCardAction?: boolean;
  onClick?: (e: any) => void;
  onDelete?: (e: any) => void;
  onEdit?: (e: any) => void;
  title: string;
}

const ListItem: React.FC<ListItemProps> = ({
  title,
  description,
  hasCardAction = false,
  hasActions = false,
  onClick,
  onEdit,
  onDelete
}): JSX.Element => {
  const { classes } = useStyles();

  const ActionArea = useMemo(
    () => (hasCardAction ? MuiCardActionArea : React.Fragment),
    [hasCardAction]
  );

  return (
    <MuiCard className={classes.listItem} variant="outlined">
      <ActionArea onClick={(e) => onClick?.(e)}>
        <MuiCardContent>
          <h3>{title}</h3>
          <p>{description}</p>
        </MuiCardContent>
      </ActionArea>
      {hasActions && (
        <MuiCardActions>
          <IconButton
            icon={<EditIcon />}
            size="small"
            variant="primary"
            onClick={(e) => onEdit?.(e)}
          />
          <IconButton
            icon={<DeleteIcon />}
            size="small"
            variant="ghost"
            onClick={(e) => onDelete?.(e)}
          />
        </MuiCardActions>
      )}
    </MuiCard>
  );
};

export { ListItem };
