import React, { RefObject, forwardRef, useMemo } from 'react';

import {
  Card as MuiCard,
  CardActionArea as MuiCardActionArea,
  CardActions as MuiCardActions,
  CardContent as MuiCardContent,
  Typography
} from '@mui/material';
import { Delete as DeleteIcon, Edit as EditIcon } from '@mui/icons-material';

import { IconButton } from '../../Button';

import { useStyles } from './ListItem.styles';

export interface ListItemProps {
  description: string;
  hasActions?: boolean;
  hasCardAction?: boolean;
  onClick?: () => void;
  onDelete?: () => void;
  onEdit?: () => void;
  title: string;
}

const ListItem = forwardRef(
  (
    {
      title,
      description,
      hasCardAction = false,
      hasActions = false,
      onClick,
      onEdit,
      onDelete
    }: ListItemProps,
    ref
  ): JSX.Element => {
    const { classes } = useStyles();

    const ActionArea = useMemo(
      () => (hasCardAction ? MuiCardActionArea : React.Fragment),
      [hasCardAction]
    );

    return (
      <MuiCard
        className={classes.listItem}
        ref={ref as RefObject<HTMLDivElement>}
        variant="outlined"
      >
        <ActionArea data-testId="action-area" onClick={onClick}>
          <MuiCardContent>
            <Typography variant="h5">{title}</Typography>
            <Typography>{description}</Typography>
          </MuiCardContent>
        </ActionArea>
        {hasActions && (
          <MuiCardActions>
            <IconButton
              data-testId="edit"
              icon={<EditIcon />}
              size="small"
              variant="primary"
              onClick={onEdit}
            />
            <IconButton
              data-testId="dashboard-delete"
              icon={<DeleteIcon />}
              size="small"
              variant="ghost"
              onClick={onDelete}
            />
          </MuiCardActions>
        )}
      </MuiCard>
    );
  }
);

export { ListItem };
