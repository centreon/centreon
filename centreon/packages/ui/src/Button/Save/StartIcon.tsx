import { CircularProgress } from '@mui/material';
import CheckIcon from '@mui/icons-material/Check';
import SaveIcon from '@mui/icons-material/Save';

interface StartIconConfigProps {
  hasLabel: boolean;
  loading: boolean;
  succeeded: boolean;
}

interface Props {
  iconSize: number;
  isSmall: boolean;
  smallIconSize: number;
  startIconConfig: StartIconConfigProps;
}

const StartIcon = ({
  isSmall,
  startIconConfig,
  smallIconSize,
  iconSize,
}: Props): JSX.Element | null => {
  const { hasLabel, loading, succeeded } = startIconConfig;

  if (!hasLabel) {
    return null;
  }

  if (succeeded) {
    return <CheckIcon />;
  }

  if (loading) {
    return (
      <CircularProgress
        color="inherit"
        size={isSmall ? smallIconSize : iconSize}
      />
    );
  }

  return <SaveIcon />;
};

export default StartIcon;
