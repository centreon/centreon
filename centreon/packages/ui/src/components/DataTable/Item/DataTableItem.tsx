import React, { forwardRef, ReactElement, RefObject, useMemo } from 'react';

import {
  Card as MuiCard,
  CardActionArea as MuiCardActionArea,
  CardActions as MuiCardActions,
  CardContent as MuiCardContent,
  Typography as MuiTypography
} from '@mui/material';
import {
  Delete as DeleteIcon,
  Settings as SettingsIcon,
  Share as ShareIcon
} from '@mui/icons-material';

import { IconButton } from '../../Button';

import { useStyles } from './DataTableItem.styles';

export interface DataTableItemProps {
  description?: string;
  hasActions?: boolean;
  hasCardAction?: boolean;
  onClick?: () => void;
  onDelete?: () => void;
  onEdit?: () => void;
  onEditAccessRights?: () => void;
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
      onDelete,
      onEditAccessRights
    }: DataTableItemProps,
    ref
  ): ReactElement => {
    const { classes } = useStyles();

    const ActionArea = useMemo(
      () => (hasCardAction ? MuiCardActionArea : React.Fragment),
      [hasCardAction]
    );

    return (
      <MuiCard
        className={classes.dataTableItem}
        data-item-title={title}
        ref={ref as RefObject<HTMLDivElement>}
        variant="outlined"
      >
        <ActionArea aria-label="view" onClick={() => onClick?.()}>
          <MuiCardContent>
            <MuiTypography variant="h5">{title}</MuiTypography>
            {description && <MuiTypography>{description}</MuiTypography>}
          </MuiCardContent>
        </ActionArea>
        {hasActions && (
          <MuiCardActions>
            <span>
              <IconButton
                aria-label="delete"
                data-testid="delete"
                icon={<DeleteIcon />}
                size="small"
                variant="ghost"
                onClick={() => onDelete?.()}
              />
            </span>
            <span>
              <IconButton
                aria-label="edit access rights"
                data-testid="edit-access-rights"
                icon={<ShareIcon />}
                size="small"
                variant="primary"
                onClick={() => onEditAccessRights?.()}
              />
              <IconButton
                aria-label="edit"
                data-testid="edit"
                icon={<SettingsIcon />}
                size="small"
                variant="primary"
                onClick={() => onEdit?.()}
              />
            </span>
          </MuiCardActions>
        )}
      </MuiCard>
    );
  }
);

export { DataTableItem };
