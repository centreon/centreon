import React, {
  forwardRef,
  ReactElement,
  RefObject,
  useMemo,
  useState
} from 'react';

import { pipe } from 'ramda';

import {
  Menu,
  Card as MuiCard,
  CardActionArea as MuiCardActionArea,
  CardActions as MuiCardActions,
  CardContent as MuiCardContent,
  Typography as MuiTypography
} from '@mui/material';
import {
  Delete as DeleteIcon,
  Settings as SettingsIcon,
  Share as ShareIcon,
  ContentCopy as DuplicateIcon,
  MoreHoriz as MoreIcon
} from '@mui/icons-material';

import { IconButton, ActionsList, ActionsListActionDivider } from '../../..';

import { useStyles } from './DataTableItem.styles';

export interface DataTableItemProps {
  description?: string;
  hasActions?: boolean;
  hasCardAction?: boolean;
  labels;
  onClick?: () => void;
  onDelete: () => void;
  onDuplicate: () => void;
  onEdit: () => void;
  onEditAccessRights: () => void;
  title: string;
}

const DataTableItem = forwardRef(
  (
    {
      labels,
      title,
      description,
      hasCardAction = false,
      hasActions = false,
      onClick,
      onEdit,
      onDelete,
      onDuplicate,
      onEditAccessRights
    }: DataTableItemProps,
    ref
  ): ReactElement => {
    const { classes } = useStyles();
    const [moreActionsOpen, setMoreActionsOpen] = useState(null);

    const closeMoreActions = (): void => setMoreActionsOpen(null);
    const openMoreActions = (event): void => setMoreActionsOpen(event.target);

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
            <MuiTypography fontWeight={500} variant="h5">
              {title}
            </MuiTypography>
            {description && <MuiTypography>{description}</MuiTypography>}
          </MuiCardContent>
        </ActionArea>
        {hasActions && (
          <MuiCardActions>
            <span />
            <span>
              <IconButton
                ariaLabel={labels.labelShare}
                title={labels.labelShare}
                onClick={pipe(onEditAccessRights, closeMoreActions)}
              >
                <ShareIcon fontSize="small" />
              </IconButton>
              <IconButton
                ariaLabel={labels.labelMoreActions}
                title={labels.labelMoreActions}
                onClick={openMoreActions}
              >
                <MoreIcon />
              </IconButton>
              <Menu
                anchorEl={moreActionsOpen}
                open={Boolean(moreActionsOpen)}
                onClose={closeMoreActions}
              >
                <ActionsList
                  actions={[
                    {
                      Icon: SettingsIcon,
                      label: labels.labelEditProperties,
                      onClick: pipe(onEdit, closeMoreActions)
                    },
                    ActionsListActionDivider.divider,
                    {
                      Icon: DuplicateIcon,
                      label: labels.labelDuplicate,
                      onClick: pipe(onDuplicate, closeMoreActions)
                    },
                    ActionsListActionDivider.divider,
                    {
                      Icon: DeleteIcon,
                      label: labels.labelDelete,
                      onClick: pipe(onDelete, closeMoreActions)
                    }
                  ]}
                />
              </Menu>
            </span>
          </MuiCardActions>
        )}
      </MuiCard>
    );
  }
);

export { DataTableItem };
