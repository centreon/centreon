import { useTranslation } from 'react-i18next';

import TitleIcon from '@mui/icons-material/Title';
import SpeedIcon from '@mui/icons-material/Speed';
import BarChartIcon from '@mui/icons-material/BarChart';

import { labelGraphType } from '../../../../translatedLabels';
import { WidgetPropertyProps } from '../../../models';
import Subtitle from '../../../../components/Subtitle';

import useSingleMetricGraphType from './useSingleMetricGraphType';
import OptionCard from './OptionCard';
import { useGraphTypeStyles } from './SingleMetricGraphType.styles';

export const options = [
  {
    icon: <TitleIcon color="disabled" sx={{ height: 60, width: 60 }} />,
    type: 'text'
  },
  {
    icon: <SpeedIcon color="disabled" sx={{ height: 60, width: 60 }} />,
    type: 'gauge'
  },
  {
    icon: (
      <BarChartIcon
        color="disabled"
        sx={{ height: 60, transform: 'rotate(90deg)', width: 60 }}
      />
    ),
    type: 'bar'
  }
];

const SingleMetricGraphType = (props: WidgetPropertyProps): JSX.Element => {
  const { classes } = useGraphTypeStyles();

  const { t } = useTranslation();

  const { value, changeType } = useSingleMetricGraphType(props);

  return (
    <div>
      <Subtitle>{t(labelGraphType)}</Subtitle>
      <div className={classes.graphTypeContainer}>
        {options.map(({ type, icon }) => (
          <OptionCard
            changeType={changeType}
            icon={icon}
            key={type}
            type={type}
            value={value}
          />
        ))}
      </div>
    </div>
  );
};

export default SingleMetricGraphType;
