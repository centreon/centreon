/* eslint-disable react/prop-types */
import { useMemo } from 'react';

import { equals, groupBy, isEmpty, isNil, path, toPairs } from 'ramda';
import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { useFormikContext } from 'formik';

import { Divider, Stack } from '@mui/material';

import { CollapsibleItem } from '@centreon/ui/components';

import { widgetPropertiesAtom } from '../atoms';
import { labelValueSettings } from '../../translatedLabels';
import Subtitle from '../../components/Subtitle';
import { Widget } from '../models';

import { useWidgetPropertiesStyles } from './widgetProperties.styles';
import ShowInputWrapper from './ShowInputWrapper';
import { useWidgetInputs, WidgetPropertiesRenderer } from './useWidgetInputs';
import SubInputs from './SubInputs';
import { getProperty } from './Inputs/utils';

interface CollapsibleWidgetPropertiesProps {
  hasGroups: boolean;
  propertyKey: string;
}

const CollapsibleWidgetProperties = ({
  propertyKey,
  hasGroups
}: CollapsibleWidgetPropertiesProps): JSX.Element | false => {
  const { t } = useTranslation();
  const { classes } = useWidgetPropertiesStyles();

  const { values } = useFormikContext<Widget>();

  const isDefaultOptions = useMemo(
    () => equals(propertyKey, 'options'),
    [propertyKey]
  );

  const prefix = useMemo(
    () => (isDefaultOptions ? '' : 'categories.'),
    [isDefaultOptions]
  );

  const widgetProperties = useWidgetInputs(
    hasGroups ? `${prefix}${propertyKey}.elements` : `${prefix}${propertyKey}`
  );

  const selectedWidgetProperties = useAtomValue(widgetPropertiesAtom);

  const groups =
    path(
      equals(propertyKey, 'options')
        ? [propertyKey, 'groups']
        : ['categories', propertyKey, 'groups'],
      selectedWidgetProperties
    ) || [];

  const hasProperties = useMemo(
    () => !isEmpty(widgetProperties),
    [widgetProperties]
  );
  const collapsibleTitle = useMemo(
    () => t(isDefaultOptions ? labelValueSettings : propertyKey),
    [isDefaultOptions]
  );

  const groupedProperties = groupBy<WidgetPropertiesRenderer, string>(
    (input) => {
      const group = groups.find(({ id }) => equals(input.group, id));

      return group?.name || '';
    }
  )(widgetProperties || []);

  return (
    hasProperties && (
      <CollapsibleItem
        defaultExpanded={isDefaultOptions}
        title={t(collapsibleTitle)}
      >
        <div className={classes.widgetProperties}>
          {toPairs(groupedProperties).map(([groupName, inputs]) => (
            <div key={groupName}>
              <Divider className={classes.groupDivider} />
              {groupName && <Subtitle>{t(groupName)}</Subtitle>}
              <div className={classes.groupContent}>
                {inputs?.map(({ Component, key, props }) => (
                  <Stack direction="column" key={key}>
                    <ShowInputWrapper {...props}>
                      <SubInputs
                        subInputs={props.subInputs}
                        value={getProperty({
                          obj: values,
                          propertyName: props.propertyName
                        })}
                      >
                        <Component
                          {...props}
                          isInGroup={!isEmpty(groupName) && !isNil(groupName)}
                        />
                      </SubInputs>
                    </ShowInputWrapper>
                  </Stack>
                ))}
              </div>
            </div>
          ))}
        </div>
      </CollapsibleItem>
    )
  );
};

export default CollapsibleWidgetProperties;
