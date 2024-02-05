import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  checkbox: {
    '&.Mui-checked': {
      color: theme.palette.common.white
    },
    '&.MuiCheckbox-indeterminate': {
      color: theme.palette.common.white
    },
    color: theme.palette.common.white
  },
  checkboxHeaderCell: {
    alignItems: 'center',
    backgroundColor: theme.palette.background.listingHeader,
    borderBottom: 'none',
    display: 'flex',
    justifyContent: 'start',
    lineHeight: 'inherit'
  },
  predefinedRowsMenu: {
    color: theme.palette.common.white
  }
}));

export { useStyles };
