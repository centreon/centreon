import { makeStyles } from 'tss-react/mui';

interface StylesProps {
  disabled: boolean;
}

export const useStyles = makeStyles<StylesProps>()((theme, { disabled }) => ({
  header: {
    [theme.breakpoints.down('sm')]: {
      flexWrap: 'wrap',
      justifyContent: 'center',
      rowGap: theme.spacing(1)
    },
    backgroundColor: disabled ? 'transparent' : 'undefined',
    border: disabled ? 'unset' : 'undefined',
    boxShadow: disabled ? 'unset' : 'undefined',
    columnGap: theme.spacing(2),
    display: 'flex',
    gridTemplateRows: '1fr',
    justifyContent: 'center'
  }
}));
