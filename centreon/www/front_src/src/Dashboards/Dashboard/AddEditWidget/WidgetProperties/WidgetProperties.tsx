import { isEmpty, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';
import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';

import { CollapsibleItem, Tooltip } from '@centreon/ui/components';

import {
  labelDescription,
  labelShowDescription,
  labelName,
  labelOpenLinksInNewTab,
  labelOpenLinksInNewTabTooltip,
  labelWidgetProperties,
  labelValueSettings
} from '../../translatedLabels';
import { Widget } from '../models';

import { WidgetRichTextEditor, WidgetSwitch, WidgetTextField } from './Inputs';
import { useWidgetInputs } from './useWidgetInputs';

const WidgetProperties = (): JSX.Element => {
  const { t } = useTranslation();
  const widgetProperties = useWidgetInputs('options');

  const isWidgetSelected = !isNil(widgetProperties);

  const hasProperties = !isEmpty(widgetProperties);

  return (
    <>
      {isWidgetSelected && (
        <CollapsibleItem defaultExpanded title={t(labelWidgetProperties)}>
          <>
            <WidgetTextField label={labelName} propertyName="name" />
            <Box
              sx={{
                alignItems: 'center',
                display: 'flex',
                justifyContent: 'space-between'
              }}
            >
              <Typography>
                <strong>{t(labelDescription)}</strong>
              </Typography>
              <WidgetSwitch
                label={labelShowDescription}
                propertyName="description.enabled"
              />
            </Box>
            <WidgetRichTextEditor
              disabledCondition={(values: Widget) =>
                !values.options.description?.enabled
              }
              label={labelDescription}
              propertyName="description.content"
            />
            <WidgetSwitch
              endAdornment={
                <Tooltip
                  followCursor={false}
                  label={t(labelOpenLinksInNewTabTooltip)}
                  position="top"
                >
                  <InfoOutlinedIcon color="primary" fontSize="small" />
                </Tooltip>
              }
              label={labelOpenLinksInNewTab}
              propertyName="openLinksInNewTab"
            />
          </>
        </CollapsibleItem>
      )}
      {isWidgetSelected && hasProperties && (
        <CollapsibleItem defaultExpanded title={t(labelValueSettings)}>
          <>
            {(widgetProperties || []).map(({ Component, key, props }) => (
              <Component key={key} {...props} />
            ))}
          </>
        </CollapsibleItem>
      )}
    </>
  );
};

export default WidgetProperties;
