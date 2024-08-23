import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import Title from '../../../../components/Title';
import { labelDatasetSelection } from '../../translatedLabels';

import { useWidgetInputs } from './useWidgetInputs';
import { useWidgetPropertiesStyles } from './widgetProperties.styles';

const WidgetData = (): JSX.Element => {
  const { t } = useTranslation();

  const { classes } = useWidgetPropertiesStyles();

  const widgetData = useWidgetInputs('data');

  const isWidgetSelected = !isNil(widgetData);

  return (
    <div>
      {isWidgetSelected && <Title>{t(labelDatasetSelection)}</Title>}
      <div className={classes.widgetDataContent}>
        {(widgetData || []).map(({ Component, key, props }) => (
          <div className={classes.widgetDataItem} key={key}>
            <Component {...props} />
          </div>
        ))}
      </div>
    </div>
  );
};

export default WidgetData;
