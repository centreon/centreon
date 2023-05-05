import { makeStyles } from 'tss-react/mui';

interface StylesProps {
  disabled: boolean;
}

export const useStyles = makeStyles<StylesProps>()((theme, { disabled }) => ({
  header: {
    alignItems: 'center',
    backgroundColor: disabled ? 'transparent' : 'undefined',
    border: disabled ? 'unset' : 'undefined',
    boxShadow: disabled ? 'unset' : 'undefined',
    columnGap: theme.spacing(2),
    display: 'grid',
    gridTemplateColumns: `repeat(4, auto)`,
    gridTemplateRows: '1fr',
    justifyContent: 'center',
    padding: theme.spacing(1, 0.5)
  }
}));
