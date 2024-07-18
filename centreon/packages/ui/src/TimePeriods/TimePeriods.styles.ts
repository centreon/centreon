import { makeStyles } from 'tss-react/mui';

interface StylesProps {
  disabled: boolean;
}

export const useStyles = makeStyles<StylesProps>()((theme, { disabled }) => ({
  condensed: {
    flexWrap: 'wrap',
    justifyContent: 'center',
    rowGap: theme.spacing(1)
  },
  header: {
    alignItems: 'center',
    backgroundColor: disabled ? 'transparent' : 'undefined',
    border: disabled ? 'unset' : 'undefined',
    boxShadow: disabled ? 'unset' : 'undefined',
    columnGap: theme.spacing(2),
    display: 'flex',
    gridTemplateRows: '1fr',
    justifyContent: 'center'
  }
}));
