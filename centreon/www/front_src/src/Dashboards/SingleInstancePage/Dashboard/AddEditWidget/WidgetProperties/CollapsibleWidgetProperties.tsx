/* eslint-disable react/prop-types */
import { useMemo } from 'react';

import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { path, equals, groupBy, isEmpty, isNil, toPairs } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Divider, Stack } from '@mui/material';

import { CollapsibleItem } from '@centreon/ui/components';

import Subtitle from '../../components/Subtitle';
import { labelValueSettings } from '../../translatedLabels';
import { widgetPropertiesAtom } from '../atoms';
import { Widget } from '../models';

import { getProperty } from './Inputs/utils';
import ShowInputWrapper from './ShowInputWrapper';
import SubInputs from './SubInputs';
import { WidgetPropertiesRenderer, useWidgetInputs } from './useWidgetInputs';
import { useWidgetPropertiesStyles } from './widgetProperties.styles';

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
