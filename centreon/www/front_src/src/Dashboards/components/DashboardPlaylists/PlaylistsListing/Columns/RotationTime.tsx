import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';

import { labelSeconds } from '../translatedLabels';

const RotationTime = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { role, rotationTime } = row;
  const isNestedRow = !isNil(role);

  if (!isNestedRow) {
    return <Box>{`${rotationTime} ${t(labelSeconds)}`}</Box>;
  }

  return <Box />;
};

export default RotationTime;
