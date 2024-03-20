import { makeStyles } from 'tss-react/mui';

interface StyleProps {
  windowHeight: number;
}

const actionsHeight = 36;

export const useStyles = makeStyles<StyleProps>()(
  (theme, { windowHeight }) => ({
    container: {
      marginBottom: 16
    },
    containerDatePicker: {
      alignItems: 'center',
      display: 'flex',
      flexDirection: 'row'
    },
    dateTimePicker: {
      '& .MuiInputBase-input': {
        width: '100%'
      },
      flex: 0.9
    },
    helperText: {
      textAlign: 'start'
    },
    popper: {
      height: (windowHeight - actionsHeight) / 2,
      overflow: 'auto'
    },
    root: {
      '> div': {
        '& div:nth-child(1)': {
          '> div': {
            '& .MuiDateCalendar-root': {
              height: theme.spacing(38.5)
            },
            '& .MuiMultiSectionDigitalClock-root': {
              maxHeight: theme.spacing(38.5)
            }
          }
        }
      }
    },
    subContainer: {
      flex: 0.1,
      paddingLeft: theme.spacing(0.25)
    }
  })
);
