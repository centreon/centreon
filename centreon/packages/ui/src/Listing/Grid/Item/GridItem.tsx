import { forwardRef, ReactElement, RefObject } from 'react';

import {
  Card as MuiCard,
  CardActionArea as MuiCardActionArea,
  CardActions as MuiCardActions,
  CardContent as MuiCardContent,
  Typography as MuiTypography
} from '@mui/material';

import Checkbox from '../../Listing/Checkbox';

import { useStyles } from './GridItem.styles';

export interface GridItemProps {
  actions?: JSX.Element;
  checkable?: boolean;
  description?: string;
  onClick?: () => void;
  title: string;
}

const GridItem = forwardRef(
  (
    { title, description, onClick, actions, checkable }: GridItemProps,
    ref
  ): ReactElement => {
    const { classes } = useStyles();

    return (
      <MuiCard
        className={classes.dataTableItem}
        data-item-title={title}
        ref={ref as RefObject<HTMLDivElement>}
        variant="outlined"
      >
        <MuiCardActionArea aria-label="view" onClick={() => onClick?.()}>
          <MuiCardContent>
            <MuiTypography fontWeight={500} variant="h5">
              {title}
            </MuiTypography>
            {description && <MuiTypography>{description}</MuiTypography>}
          </MuiCardContent>
        </MuiCardActionArea>
        <MuiCardActions>
          {checkable && <Checkbox className={classes.checkbox} />}
          {actions && actions}
        </MuiCardActions>
      </MuiCard>
    );
  }
);

export default GridItem;
