import { useTheme } from '@mui/material';

import { DowntimeIcon } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import { labelDowntime } from '../../../../../translatedLabels';
import { Props } from '..';
import EventAnnotations from '../EventAnnotations';

const DowntimeAnnotations = (props: Props): JSX.Element => {
  const { t } = useTranslation();
  const theme = useTheme();

  const color = theme.palette.action.inDowntime;

  return (
    <EventAnnotations
      ariaLabel={t(labelDowntime)}
      color={color}
      Icon={DowntimeIcon}
      type="downtime"
      {...props}
    />
  );
};

export default DowntimeAnnotations;
