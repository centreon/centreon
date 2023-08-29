import { useRef } from 'react';

import { useFormikContext } from 'formik';
import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { RichTextEditor } from '@centreon/ui';

import FederatedComponent from '../../../../components/FederatedComponents';
import { Widget } from '../models';
import { labelPleaseChooseAWidgetToActivatePreview } from '../../translatedLabels';
import { isGenericText } from '../../utils';

import { useWidgetPropertiesStyles } from './widgetProperties.styles';

const Preview = (): JSX.Element | null => {
  const { t } = useTranslation();
  const { classes } = useWidgetPropertiesStyles();

  const previewRef = useRef<HTMLDivElement | null>(null);

  const { values } = useFormikContext<Widget>();

  if (isNil(values.id)) {
    return (
      <Typography variant="h5">
        {t(labelPleaseChooseAWidgetToActivatePreview)}
      </Typography>
    );
  }

  return (
    <div className={classes.previewPanelContainer} ref={previewRef}>
      <div
        style={{
          height: `${previewRef.current?.getBoundingClientRect().height || 0}px`
        }}
      >
        {isGenericText(values.panelConfiguration?.path) ? (
          <RichTextEditor
            editable={false}
            editorState={
              values.options?.description?.enabled
                ? values.options?.description?.content
                : undefined
            }
          />
        ) : (
          <FederatedComponent
            isFederatedWidget
            id={values.id}
            panelData={values.data}
            panelOptions={values.options}
            path={values.panelConfiguration?.path || ''}
          />
        )}
      </div>
    </div>
  );
};

export default Preview;
