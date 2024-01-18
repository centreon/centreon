import { useTranslation } from 'react-i18next';

import { Image, LoadingSkeleton } from '@centreon/ui';

import {
  labelDisplayType,
  labelViewByHost,
  labelViewByService,
  labelAll
} from '../../../../translatedLabels';
import { WidgetPropertyProps } from '../../../models';
import Subtitle from '../../../../components/Subtitle';

import useDisplayType from './useDisplayType';
import Option from './Option';
import { useStyles } from './DisplayType.styles';
import { getIconbyView } from './icons/getIconByView';

export const options = [
  {
    icon: (
      <Image
        alt={labelAll}
        fallback={<LoadingSkeleton height={80} width={80} />}
        imagePath={getIconbyView('all')}
      />
    ),
    label: labelAll,
    type: 'all'
  },
  {
    icon: (
      <Image
        alt={labelAll}
        fallback={<LoadingSkeleton height={80} width={80} />}
        imagePath={getIconbyView('host')}
      />
    ),
    label: labelViewByHost,
    type: 'host'
  },
  {
    icon: (
      <Image
        alt={labelAll}
        fallback={<LoadingSkeleton height={80} width={80} />}
        imagePath={getIconbyView('service')}
      />
    ),
    label: labelViewByService,
    type: 'service'
  }
];

const DisplayType = (props: WidgetPropertyProps): JSX.Element => {
  const { classes } = useStyles();

  const { t } = useTranslation();

  const { value, changeType } = useDisplayType(props);

  return (
    <div>
      <Subtitle>{t(labelDisplayType)}</Subtitle>
      <div className={classes.container}>
        {options.map(({ type, icon, label }) => (
          <Option
            changeType={changeType}
            icon={icon}
            key={type}
            label={label}
            type={type}
            value={value}
          />
        ))}
      </div>
    </div>
  );
};

export default DisplayType;
