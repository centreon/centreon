import { equals, groupBy, isEmpty, isNil, toPairs } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';

import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';
import { Divider, Typography } from '@mui/material';

import { CollapsibleItem, Tooltip } from '@centreon/ui/components';

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
import { widgetPropertiesAtom } from '../atoms';

import { WidgetRichTextEditor, WidgetSwitch, WidgetTextField } from './Inputs';
import { useWidgetInputs, WidgetPropertiesRenderer } from './useWidgetInputs';
import { useWidgetPropertiesStyles } from './widgetProperties.styles';
import ShowInputWrapper from './ShowInputWrapper';

const WidgetProperties = (): JSX.Element => {
  const { t } = useTranslation();

  const { classes } = useWidgetPropertiesStyles();

  const widgetOptions = useWidgetInputs('options');
  const widgetProperties = useWidgetInputs('generalProperties.elements');
  const selectedWidgetProperties = useAtomValue(widgetPropertiesAtom);

  const groups = selectedWidgetProperties?.generalProperties?.groups || [];
  const isWidgetSelected = !isNil(widgetOptions);

  const hasOptions = !isEmpty(widgetOptions);
  const hasProperties = !isEmpty(widgetProperties);

  const groupedGeneralProperties = groupBy<WidgetPropertiesRenderer, string>(
    (input) => {
      const group = groups.find(({ id }) => equals(input.group, id));

      return group?.name || '';
    }
  )(widgetProperties || []);

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
            {isWidgetSelected && hasProperties && (
              <div className={classes.widgetProperties}>
                {toPairs(groupedGeneralProperties).map(
                  ([groupName, inputs]) => (
                    <div key={groupName}>
                      <Divider className={classes.groupDivider} />
                      {groupName && (
                        <Typography className={classes.groupTitle} variant="h6">
                          {t(groupName)}
                        </Typography>
                      )}
                      <div className={classes.groupContent}>
                        {inputs?.map(({ Component, key, props }) => (
                          <div key={key}>
                            <ShowInputWrapper {...props}>
                              <Component {...props} />
                            </ShowInputWrapper>
                          </div>
                        ))}
                      </div>
                    </div>
                  )
                )}
              </div>
            )}
          </div>
        </CollapsibleItem>
      )}
      {isWidgetSelected && hasOptions && (
        <CollapsibleItem defaultExpanded title={t(labelValueSettings)}>
          <div className={classes.widgetProperties}>
            {(widgetOptions || []).map(({ Component, key, props }) => (
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
