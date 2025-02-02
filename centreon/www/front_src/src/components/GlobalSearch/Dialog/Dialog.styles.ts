import { alpha } from '@mui/system';
import { makeStyles } from 'tss-react/mui';

export const useDialogStyles = makeStyles()((theme) => ({
  dialogWrapper: {
    position: 'absolute',
    width: '100%',
    height: '100%',
    zIndex: theme.zIndex.tooltip + 1,
    backdropFilter: 'blur(2px) brightness(90%)',
    transition: 'backdrop-filter 0.3s ease-out',
    overflow: 'hidden'
  },
  dialogWrapperClosed: {
    backdropFilter: 'blur(0px) brightness(100%)'
  },
  displayWrapperNone: {
    display: 'none'
  },
  dialog: {
    top: '10%',
    left: '50%',
    transform: 'translate3d(-50%, 0px, 0px)',
    position: 'absolute',
    width: '60%',
    transition: 'transform 0.3s ease-out',
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1.5)
  },
  dialogClosed: {
    transform: 'translate3d(-50%, -200px, 0px)'
  },
  listItem: {
    '&[data-selected="true"],&:hover': {
      '& svg': {
        fill: theme.palette.primary.main
      },
      color: theme.palette.primary.main,
      backgroundColor: alpha(theme.palette.primary.main, 0.2)
    },
    cursor: 'pointer'
  }
}));
