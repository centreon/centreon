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
      gap: theme.spacing(1)
    },
    picker: {
      '& .MuiInputBase-root': {
        '& .MuiInputBase-input': {
          width: '100%'
        },
        backgroundColor: theme.palette.background.default,
        width: theme.spacing(34.5)
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
