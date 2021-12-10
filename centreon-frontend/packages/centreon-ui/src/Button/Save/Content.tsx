import * as React from 'react';

import { useTranslation } from 'react-i18next';

import { CircularProgress } from '@material-ui/core';
import CheckIcon from '@material-ui/icons/Check';
import SaveIcon from '@material-ui/icons/Save';

interface Props {
  iconSize: number;
  isSmall: boolean;
  labelLoading: string;
  labelSave: string;
  labelSucceeded: string;
  loading: boolean;
  smallIconSize: number;
  succeeded: boolean;
}

const Content = ({
  succeeded,
  labelSucceeded,
  isSmall,
  smallIconSize,
  labelLoading,
  loading,
  iconSize,
  labelSave,
}: Props): JSX.Element | string => {
  const { t } = useTranslation();

  if (succeeded) {
    return labelSucceeded ? t(labelSucceeded) : <CheckIcon />;
  }

  if (loading) {
    return labelLoading ? (
      t(labelLoading)
    ) : (
      <CircularProgress
        color="inherit"
        size={isSmall ? smallIconSize : iconSize}
      />
    );
  }

  return labelSave ? t(labelSave) : <SaveIcon />;
};

export default Content;
