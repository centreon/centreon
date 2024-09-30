import { Suspense, useRef } from 'react';

import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { equals, find, isEmpty, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';
import { Typography } from '@mui/material';

import { RichTextEditor } from '@centreon/ui';

import FederatedComponent from '../../../../../components/FederatedComponents';
import { dashboardRefreshIntervalAtom } from '../../atoms';
import DescriptionWrapper from '../../components/DescriptionWrapper';
import { useCanEditProperties } from '../../hooks/useCanEditDashboard';
import {
  labelPleaseChooseAWidgetToActivatePreview,
  labelPleaseContactYourAdministrator,
  labelYourRightsOnlyAllowToView
} from '../../translatedLabels';
import { isGenericText, isRichTextEditorEmpty } from '../../utils';
import { Widget } from '../models';

import { federatedWidgetsAtom } from '@centreon/ui-context';
import { FederatedModule } from '../../../../../federatedModules/models';
import { useWidgetPropertiesStyles } from './widgetProperties.styles';

const Preview = (): JSX.Element | null => {
  const { t } = useTranslation();
  const { classes, cx } = useWidgetPropertiesStyles();

  const refreshInterval = useAtomValue(dashboardRefreshIntervalAtom);
  const federatedWidgets = useAtomValue(federatedWidgetsAtom);

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

  const { Component, remoteEntry } = find(
    (widget) => equals(widget.moduleName, values.moduleName),
    federatedWidgets as Array<FederatedModule>
  ) as FederatedModule;

  const isGenericTextPanel = isGenericText(values.panelConfiguration?.path);

  const displayDescription =
    !isGenericTextPanel &&
    values.options?.description?.enabled &&
    values.options?.description?.content &&
    !isRichTextEditorEmpty(values.options?.description?.content);

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
        {displayDescription && (
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
          {!isEmpty(remoteEntry) || isNil(Component) ? (
            <FederatedComponent
              isFederatedWidget
              isFromPreview
              globalRefreshInterval={refreshInterval}
              id={values.id}
              panelData={values.data}
              panelOptions={values.options}
              path={values.panelConfiguration?.path || ''}
              setPanelOptions={changePanelOptions}
              hasDescription={displayDescription}
            />
          ) : (
            <Suspense fallback={<div>Loading...</div>}>
              <Component
                isFromPreview
                globalRefreshInterval={refreshInterval}
                panelData={values.data}
                panelOptions={values.options}
                path={values.panelConfiguration?.path || ''}
                setPanelOptions={changePanelOptions}
                hasDescription={displayDescription}
              />
            </Suspense>
          )}
        </div>
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
