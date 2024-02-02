import { makeStyles } from 'tss-react/mui';

export const useBaseColorStyles = makeStyles()((theme) => ({
  container: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(2)
  },
  option: {
    borderRadius: '50%',
    borderStyle: 'solid',
    borderWidth: '1px',
    cursor: 'pointer',
    padding: theme.spacing(0.5),
    transition: `border-color ease-out ${theme.transitions.duration.standard}`
  },
  optionContent: {
    borderRadius: '50%',
    height: theme.spacing(2),
    width: theme.spacing(2)
  },
  options: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1)
  }
}));
