import { useRef } from 'react';

import { useFormikContext } from 'formik';
import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';

import { Typography } from '@mui/material';
import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';

import { RichTextEditor } from '@centreon/ui';

import FederatedComponent from '../../../../../components/FederatedComponents';
import { Widget } from '../models';
import {
  labelPleaseChooseAWidgetToActivatePreview,
  labelPleaseContactYourAdministrator,
  labelYourRightsOnlyAllowToView
} from '../../translatedLabels';
import { isGenericText } from '../../utils';
import { useCanEditProperties } from '../../hooks/useCanEditDashboard';
import { dashboardRefreshIntervalAtom } from '../../atoms';
import DescriptionWrapper from '../../components/DescriptionWrapper';

import { useWidgetPropertiesStyles } from './widgetProperties.styles';

const Preview = (): JSX.Element | null => {
  const { t } = useTranslation();
  const { classes, cx } = useWidgetPropertiesStyles();

  const refreshInterval = useAtomValue(dashboardRefreshIntervalAtom);

  const { canEdit } = useCanEditProperties();

  const previewRef = useRef<HTMLDivElement | null>(null);

  const { values, setFieldValue } = useFormikContext<Widget>();

  if (isNil(values.id)) {
    return (
      <Typography variant="h5">
        {t(labelPleaseChooseAWidgetToActivatePreview)}
      </Typography>
    );
  }

  const isGenericTextWidget = isGenericText(values.panelConfiguration?.path);

  const changePanelOptions = (partialOptions: object): void => {
    Object.entries(partialOptions).forEach(([key, value]) => {
      setFieldValue(`options.${key}`, value, false);
    });
  };

  return (
    <div className={classes.previewPanelContainer} ref={previewRef}>
      <div
        style={{
          height: `${
            (previewRef.current?.getBoundingClientRect().height || 0) - 16
          }px`,
          overflowY: 'auto'
        }}
      >
        <Typography
          className={cx(classes.previewHeading, classes.previewTitle)}
          variant="button"
        >
          {values.options?.name}
        </Typography>
        {values.options?.description?.enabled && (
          <DescriptionWrapper>
            <RichTextEditor
              disabled
              contentClassName={classes.previewHeading}
              editable={false}
              editorState={
                values.options?.description?.enabled
                  ? values.options?.description?.content || undefined
                  : undefined
              }
            />
          </DescriptionWrapper>
        )}
        {!isGenericTextWidget && (
          <div
            style={{
              height: `${
                (previewRef.current?.getBoundingClientRect().height || 0) -
                36 -
                46
              }px`,
              overflow: 'auto',
              position: 'relative'
            }}
          >
            <FederatedComponent
              isFederatedWidget
              isFromPreview
              globalRefreshInterval={refreshInterval}
              id={values.id}
              panelData={values.data}
              panelOptions={values.options}
              path={values.panelConfiguration?.path || ''}
              setPanelOptions={changePanelOptions}
            />
          </div>
        )}
      </div>
      {!canEdit && (
        <div className={classes.previewUserRightPanel}>
          <div className={classes.previewUserRightPanelContent}>
            <InfoOutlinedIcon sx={{ mt: 0.5 }} />
            <div>
              <Typography variant="subtitle1">
                {t(labelYourRightsOnlyAllowToView)}
              </Typography>
              <Typography variant="subtitle1">
                {t(labelPleaseContactYourAdministrator)}
              </Typography>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Preview;
