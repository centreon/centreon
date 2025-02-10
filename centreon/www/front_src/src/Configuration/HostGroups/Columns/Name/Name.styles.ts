import { makeStyles } from 'tss-react/mui';

export const useNameStyles = makeStyles<{ isHovered: boolean }>()(
  (theme, { isHovered }) => ({
    container: {
      display: 'flex',
      gap: theme.spacing(0.5),
      alignItems: 'center'
    },
    resourceNameText: {
      color: isHovered
        ? theme.palette.text.primary
        : theme.palette.text.secondary
    }
  })
);
