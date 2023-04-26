import { useTranslation } from 'react-i18next';

import CheckIcon from '@mui/icons-material/Check';
import SaveIcon from '@mui/icons-material/Save';

interface Props {
  labelLoading: string;
  labelSave: string;
  labelSucceeded: string;
  loading: boolean;
  succeeded: boolean;
}

const Content = ({
  succeeded,
  labelSucceeded,
  labelSave,
  loading,
  labelLoading
}: Props): JSX.Element | string | null => {
  const { t } = useTranslation();

  if (loading) {
    return t(labelLoading);
  }

  if (succeeded) {
    return labelSucceeded ? t(labelSucceeded) : <CheckIcon />;
  }

  return labelSave ? t(labelSave) : <SaveIcon />;
};

export default Content;
