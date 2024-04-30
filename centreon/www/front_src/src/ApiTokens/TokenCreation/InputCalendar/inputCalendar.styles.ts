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
    error: {
      paddingLeft: theme.spacing(6.5),
      textAlign: 'start'
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
    popperContainer: {
      '& .MuiPaper-root': {
        backgroundColor: theme.palette.background.default
      },
      zIndex: theme.zIndex.tooltip
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
    secondaryContainer: {
      padding: theme.spacing(0, 0.5)
    },
    subContainer: {
      flex: 0.1,
      paddingLeft: theme.spacing(0.5)
    }
  })
);
