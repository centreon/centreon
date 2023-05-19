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

import { useStyles } from './DataTableItem.styles';

export interface DataTableItemProps {
  description?: string;
  hasActions?: boolean;
  hasCardAction?: boolean;
  onClick?: () => void;
  onDelete?: () => void;
  onEdit?: () => void;
  title: string;
}

const DataTableItem = forwardRef(
  (
    {
      title,
      description,
      hasCardAction = false,
      hasActions = false,
      onClick,
      onEdit,
      onDelete
    }: DataTableItemProps,
    ref
  ): JSX.Element => {
    const { classes } = useStyles();

    const ActionArea = useMemo(
      () => (hasCardAction ? MuiCardActionArea : React.Fragment),
      [hasCardAction]
    );

    return (
      <MuiCard
        className={classes.dataTableItem}
        ref={ref as RefObject<HTMLDivElement>}
        variant="outlined"
      >
        <ActionArea aria-label="view" onClick={() => onClick?.()}>
          <MuiCardContent>
            <Typography variant="h5">{title}</Typography>
            {description && <Typography>{description}</Typography>}
          </MuiCardContent>
        </ActionArea>
        {hasActions && (
          <MuiCardActions>
            <IconButton
              aria-label="edit"
              icon={<EditIcon />}
              size="small"
              variant="primary"
              onClick={() => onEdit?.()}
            />
            <IconButton
              aria-label="delete"
              icon={<DeleteIcon />}
              size="small"
              variant="ghost"
              onClick={() => onDelete?.()}
            />
          </MuiCardActions>
        )}
      </MuiCard>
    );
  }
);

export { DataTableItem };
