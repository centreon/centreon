import { isEmpty, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box, Divider, Typography } from '@mui/material';
import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';

import { Tooltip } from '@centreon/ui/components';

import {
  labelCommonProperties,
  labelDescription,
  labelShowDescription,
  labelName,
  labelOpenLinksInNewTab,
  labelOpenLinksInNewTabTooltip,
  labelWidgetProperties
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
        <>
          <Typography variant="h6">{t(labelWidgetProperties)}</Typography>
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
          {hasProperties && (
            <>
              <Divider variant="middle" />
              <Typography>
                <strong>{t(labelCommonProperties)}</strong>
              </Typography>
            </>
          )}
        </>
      )}
      {(widgetProperties || []).map(({ Component, key, props }) => (
        <Component key={key} {...props} />
      ))}
    </>
  );
};

export default WidgetProperties;
