import { makeStyles } from 'tss-react/mui';

interface StyleProps {
  windowHeight: number;
}

const actionsHeight = 36;

export const useStyles = makeStyles<StyleProps>()(
  (theme, { windowHeight }) => ({
    containerDatePicker: {
      alignItems: 'center',
      display: 'flex',
      justifyContent: 'space-between',
      gap: theme.spacing(1)
    },
    picker: {
      '& .MuiInputBase-root': {
        '& .MuiInputBase-input': {
          width: '100%'
        },
        width: theme.spacing(32)
      }
    },
    popper: {
      height: (windowHeight - actionsHeight) / 2,
      overflow: 'auto'
    },
    secondaryContainer: {
      padding: theme.spacing(0, 0.5)
    }
  })
);
