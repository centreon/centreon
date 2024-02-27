import { makeStyles } from 'tss-react/mui';

interface StyleProps {
  marginWidthTableListing: number;
  width: number;
}

const useStyles = makeStyles<StyleProps>()(
  (theme, { width, marginWidthTableListing }) => ({
    ModeViewer: {
      paddingLeft: theme.spacing(1)
    },
    actions: {
      alignItems: 'center',
      display: 'flex',
      gap: theme.spacing(3),
      padding: theme.spacing(1, 0)
    },
    container: {
      alignItems: 'center',
      display: 'flex',
      flexWrap: 'wrap',
      justifyContent: 'space-between',
      width: '100%'
    },
    iconMode: {
      '& .MuiSvgIcon-root': {
        height: theme.spacing(1.5)
      },
      display: 'flex',
      flexDirection: 'column'
    },
    mode: {
      flexDirection: 'column-reverse'
    },
    moving: {
      marginRight: theme.spacing((width - marginWidthTableListing) / 8)
    },
    pagination: {
      '& .MuiToolbar-root': {
        paddingLeft: 0
      },
      padding: 0
    },
    selectMenu: {
      '& .MuiMenuItem-root': {
        lineHeight: 1
      }
    },
    subContainer: {
      alignItems: 'center',
      display: 'flex'
    }
  })
);

export const useViewModeStyles = makeStyles()((theme) => ({
  viewMode: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(1)
  }
}));

export const usePaginationStyles = makeStyles()((theme) => ({
  root: {
    color: theme.palette.text.secondary,
    flexShrink: 0
  },
  toolbar: {
    height: theme.spacing(4),
    overflow: 'hidden',
    paddingLeft: 5
  }
}));

export default useStyles;
