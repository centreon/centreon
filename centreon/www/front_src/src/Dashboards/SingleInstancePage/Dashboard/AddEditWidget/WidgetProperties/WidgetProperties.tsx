import { equals, isEmpty, isNil, keys, path } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';

import { CollapsibleItem } from '@centreon/ui/components';

import {
  labelDescription,
  labelShowDescription,
  labelWidgetProperties,
  labelTitle
} from '../../translatedLabels';
import Subtitle from '../../components/Subtitle';
import { widgetPropertiesAtom } from '../atoms';

import { WidgetRichTextEditor, WidgetSwitch, WidgetTextField } from './Inputs';
import { useWidgetPropertiesStyles } from './widgetProperties.styles';
import CollapsibleWidgetProperties from './CollapsibleWidgetProperties';

const WidgetProperties = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useWidgetPropertiesStyles();

  const selectedWidgetProperties = useAtomValue(widgetPropertiesAtom);

  const isWidgetSelected = !isNil(selectedWidgetProperties);

  const inputCategories = isWidgetSelected
    ? ['options', ...keys(selectedWidgetProperties?.categories || {})]
    : [];

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
            </div>
          </div>
        </CollapsibleItem>
      )}
      {inputCategories.map((category) => (
        <CollapsibleWidgetProperties
          hasGroups={
            !isEmpty(
              path(
                equals(category, 'options')
                  ? [category, 'groups']
                  : ['categories', category, 'groups'],
                selectedWidgetProperties
              )
            ) &&
            !isNil(
              path(
                equals(category, 'options')
                  ? [category, 'groups']
                  : ['categories', category, 'groups'],
                selectedWidgetProperties
              )
            )
          }
          key={category}
          propertyKey={category}
        />
      ))}
    </div>
  );
};

export default WidgetProperties;
