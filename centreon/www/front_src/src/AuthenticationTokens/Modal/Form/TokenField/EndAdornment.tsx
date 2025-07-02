import Visibility from '@mui/icons-material/Visibility';
import VisibilityOff from '@mui/icons-material/VisibilityOff';

import { IconButton } from '@centreon/ui';

interface Props {
  isVisible: boolean;
  onClick: () => void;
}

export const endAdornment =
  ({ isVisible, onClick }: Props) =>
  () => {
    return (
      <IconButton
        aria-label="toggle password visibility"
        edge="end"
        style={{ marginRight: 4 }}
        onClick={onClick}
      >
        {isVisible ? <Visibility /> : <VisibilityOff />}
      </IconButton>
    );
  };
