import React, { forwardRef, ReactElement, RefObject, useMemo } from 'react';

import {
  Card as MuiCard,
  CardActionArea as MuiCardActionArea,
  CardActions as MuiCardActions,
  CardContent as MuiCardContent,
  Typography as MuiTypography
} from '@mui/material';

import { useStyles } from './DataTableItem.styles';

export interface DataTableItemProps {
  Actions?: JSX.Element;
  fovoriteAction?: JSX.Element;
  description?: string;
  hasActions?: boolean;
  hasCardAction?: boolean;
  onClick?: () => void;
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
      fovoriteAction
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
            <MuiTypography fontWeight={500} variant="h5">
              {title}
            </MuiTypography>
            {description && <MuiTypography>{description}</MuiTypography>}
          </MuiCardContent>
        </ActionArea>
          <MuiCardActions>
            <span />
            <span>{hasActions ? Actions : fovoriteAction}</span>
          </MuiCardActions>
      </MuiCard>
    );
  }
);

export { DataTableItem };
