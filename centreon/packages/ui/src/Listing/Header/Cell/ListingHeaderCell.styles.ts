import { makeStyles } from 'tss-react/mui';

import { ListingVariant } from '@centreon/ui-context';

import { getTextStyleByViewMode } from '../../useStyleTable';

interface StylesProps {
  isDragging?: boolean;
  isInDragOverlay?: boolean;
  viewMode?: ListingVariant;
}

const useStyles = makeStyles<StylesProps>()(
  (theme, { isDragging, isInDragOverlay, viewMode }) => ({
    active: {
      '&.Mui-active': {
        '& .MuiTableSortLabel-icon': {
          color: theme.palette.common.white
        },
        color: theme.palette.common.white
      },
      '&:hover': {
        '& .MuiTableSortLabel-icon': {
          opacity: 1
        },
        color: theme.palette.common.white
      }
    },
    content: {
      alignItems: 'center',
      borderRadius: isDragging && isInDragOverlay ? theme.spacing(0.5) : 0,
      color: theme.palette.common.white,
      display: 'flex',
      height: '100%',
      justifyContent: 'space-between'
    },
    dragHandle: {
      '&, &.Mui-focus, &:focus': {
        color: theme.palette.common.white
      },
      color: theme.palette.common.white,

      cursor: isDragging ? 'grabbing' : 'grab',
      opacity: 0,
      padding: 0
    },
    simpleHeaderCellContent: {
      alignItems: 'center',
      display: 'inline-flex',
      marginRight: theme.spacing(2)
    },
    tableCell: {
      backgroundColor: isInDragOverlay
        ? 'transparent'
        : theme.palette.background.listingHeader,
      borderBottom: 'none',
      height: 'inherit',
      padding: theme.spacing(0, 1),
      ...getTextStyleByViewMode({ theme, viewMode }),
      '&:hover, &:focus, &:focus-within, &[data-isDragging=true]': {
        '& .dragHandle': {
          opacity: 1
        }
      },
      '&[data-isInDragOverlay=true]': {
        display: 'block'
      }
    }
  })
);

export { useStyles, StylesProps };
