import { Theme } from '@mui/material';
import { alpha } from '@mui/system';
import { makeStyles } from 'tss-react/mui';

interface StyleProps {
  isHovered: boolean;
  isRowDisabled: boolean;
}

interface GetRowTextColorProps {
  isHovered: boolean;
  isRowDisabled: boolean;
  theme: Theme;
}
const getRowTextColor = ({
  isHovered,
  isRowDisabled,
  theme
}: GetRowTextColorProps): string => {
  if (isHovered) {
    return theme.palette.text.primary;
  }

  if (isRowDisabled) {
    return alpha(theme.palette.text.secondary, 0.5);
  }

  return theme.palette.text.secondary;
};

const useNameStyles = makeStyles<StyleProps>()(
  (theme, { isHovered, isRowDisabled }) => ({
    container: {
      display: 'flex',
      alignItems: 'center',
      gap: theme.spacing(0.5)
    },
    resourceNameText: {
      color: getRowTextColor({ theme, isHovered, isRowDisabled })
    }
  })
);

export default useNameStyles;
