import { useFormikContext } from 'formik';
import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import FederatedComponent from '../../../../components/FederatedComponents';
import { Widget } from '../models';
import { labelPleaseChooseAWidgetToActivatePreview } from '../../translatedLabels';

import { useWidgetPropertiesStyles } from './widgetProperties.styles';

const Preview = (): JSX.Element | null => {
  const { t } = useTranslation();
  const { classes } = useWidgetPropertiesStyles();

  const { values } = useFormikContext<Widget>();

  if (isNil(values.id)) {
    return (
      <Typography variant="h5">
        {t(labelPleaseChooseAWidgetToActivatePreview)}
      </Typography>
    );
  }

  return (
    <div className={classes.previewPanel}>
      <FederatedComponent
        isFederatedWidget
        id={values.id}
        panelOptions={values.options}
        path={values.panelConfiguration?.path || ''}
      />
    </div>
  );
};

export default Preview;
