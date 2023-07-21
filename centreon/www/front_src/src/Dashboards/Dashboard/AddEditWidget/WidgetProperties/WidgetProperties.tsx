import { isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Divider, Typography } from '@mui/material';

import {
  labelCommonProperties,
  labelDescription,
  labelName,
  labelWidgetProperties
} from '../../translatedLabels';

import { WidgetTextField } from './Inputs';
import { useWidgetProperties } from './useWidgetProperties';

const WidgetProperties = (): JSX.Element => {
  const { t } = useTranslation();
  const widgetProperties = useWidgetProperties();

  const hasProperties = !isEmpty(widgetProperties);

  return (
    <>
      {hasProperties && (
        <>
          <Typography variant="h6">{t(labelWidgetProperties)}</Typography>
          <WidgetTextField label={labelName} propertyName="name" />
          <WidgetTextField
            label={labelDescription}
            propertyName="description"
            text={{ multiline: true }}
          />
          <Divider variant="middle" />
          <Typography>
            <strong>{t(labelCommonProperties)}</strong>
          </Typography>
        </>
      )}
      {widgetProperties.map(({ Component, key, props }) => (
        <Component key={key} {...props} />
      ))}
    </>
  );
};

export default WidgetProperties;
