import { makeStyles } from 'tss-react/mui';

interface StyleProps {
  windowHeight: number;
}

const actionsHeight = 36;

export const useStyles = makeStyles<StyleProps>()(
  (theme, { windowHeight }) => ({
    dateTimePicker: {
      '& .MuiInputBase-input': {
        width: '100%'
      },
      flex: 0.9
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
    }
  })
);
