import { useTranslation } from 'react-i18next';

import { useTheme } from '@mui/material';

import { DowntimeIcon } from '@centreon/ui';

import { labelDowntime } from '../../../../../translatedLabels';
import { Props } from '..';
import EventAnnotations from '../EventAnnotations';

const DowntimeAnnotations = (props: Props): JSX.Element => {
  const { t } = useTranslation();
  const theme = useTheme();

  const color = theme.palette.action.inDowntime;

  return (
    <EventAnnotations
      Icon={DowntimeIcon}
      ariaLabel={t(labelDowntime)}
      color={color}
      type="downtime"
      {...props}
    />
  );
};

export default DowntimeAnnotations;
