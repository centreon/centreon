import {
  Card as MuiCard,
  CardActionArea as MuiCardActionArea,
  CardActions as MuiCardActions,
  CardContent as MuiCardContent,
  Typography as MuiTypography
} from '@mui/material';

import React, {
  forwardRef,
  type ReactElement,
  type RefObject,
  useMemo
} from 'react';

import { useStyles } from './DataTableItem.styles';

export interface DataTableItemProps {
  Actions?: JSX.Element;
  description?: string;
  hasActions?: boolean;
  hasCardAction?: boolean;
  onClick?: () => void;
  thumbnail?: string | null;
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
      Actions,
      thumbnail
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
        <MuiCardContent className={classes.cardContent}>
          <div className={classes.cardContentText}>
            <MuiTypography
              className={classes.title}
              fontWeight={500}
              variant="h6"
            >
              {title}
            </MuiTypography>
            {description && (
              <MuiTypography className={classes.description}>
                {description}
              </MuiTypography>
            )}
          </div>
          {hasActions && (
            <MuiCardActions className={classes.cardActions}>
              {Actions}
            </MuiCardActions>
          )}
        </MuiCardContent>
        <ActionArea
          aria-label="view"
          className={classes.thumbnailArea}
          onClick={() => onClick?.()}
        >
          {thumbnail && (
            <img
              alt={`thumbnail-${title}-${description}`}
              className={classes.thumbnail}
              data-testid={`thumbnail-${title}-${description}`}
              loading="lazy"
              src={thumbnail}
            />
          )}
        </ActionArea>
      </MuiCard>
    );
  }
);

export { DataTableItem };
