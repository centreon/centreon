import IconAcknowledge from '@mui/icons-material/Person';
import { useTheme } from '@mui/material';

import { useTranslation } from 'react-i18next';

import { labelAcknowledgement } from '../../../../../translatedLabels';
import { Props } from '..';
import EventAnnotations from '../EventAnnotations';

const AcknowledgementAnnotations = (props: Props): JSX.Element => {
  const { t } = useTranslation();
  const theme = useTheme();

  const color = theme.palette.action.acknowledged;

  return (
    <EventAnnotations
      ariaLabel={t(labelAcknowledgement)}
      color={color}
      Icon={IconAcknowledge}
      type="acknowledgement"
      {...props}
    />
  );
};

export default AcknowledgementAnnotations;
