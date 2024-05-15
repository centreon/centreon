import { isEmpty, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';

import { CollapsibleItem } from '@centreon/ui/components';

import {
  labelDescription,
  labelShowDescription,
  labelOpenLinksInNewTab,
  labelOpenLinksInNewTabTooltip,
  labelWidgetProperties,
  labelValueSettings,
  labelTitle
} from '../../translatedLabels';
import Subtitle from '../../components/Subtitle';

import { WidgetRichTextEditor, WidgetSwitch, WidgetTextField } from './Inputs';
import { useWidgetInputs } from './useWidgetInputs';
import { useWidgetPropertiesStyles } from './widgetProperties.styles';
import ShowInputWrapper from './ShowInputWrapper';

const WidgetProperties = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useWidgetPropertiesStyles();

  const widgetProperties = useWidgetInputs('options');

  const isWidgetSelected = !isNil(widgetProperties);

  const hasProperties = !isEmpty(widgetProperties);

  return (
    <div className={classes.widgetPropertiesContainer}>
      {isWidgetSelected && (
        <CollapsibleItem defaultExpanded title={t(labelWidgetProperties)}>
          <div className={classes.widgetProperties}>
            <WidgetTextField label={labelTitle} propertyName="name" />
            <div>
              <Subtitle>{t(labelDescription)}</Subtitle>
              <div className={classes.widgetDescription}>
                <WidgetRichTextEditor
                  label={labelDescription}
                  propertyName="description.content"
                />
              </div>
              <WidgetSwitch
                label={labelShowDescription}
                propertyName="description.enabled"
              />
              <WidgetSwitch
                endAdornment={
                  <Tooltip
                    followCursor={false}
                    label={t(labelOpenLinksInNewTabTooltip)}
                    position="right"
                  >
                    <InfoOutlinedIcon color="primary" fontSize="small" />
                  </Tooltip>
                }
                label={labelOpenLinksInNewTab}
                propertyName="openLinksInNewTab"
              />
            </div>
          </div>
        </CollapsibleItem>
      )}
      {isWidgetSelected && hasProperties && (
        <CollapsibleItem defaultExpanded title={t(labelValueSettings)}>
          <div className={classes.widgetProperties}>
            {(widgetProperties || []).map(({ Component, key, props }) => (
              <div key={key}>
                <ShowInputWrapper {...props}>
                  <Component {...props} />
                </ShowInputWrapper>
              </div>
            ))}
          </div>
        </CollapsibleItem>
      )}
    </div>
  );
};

export default WidgetProperties;
