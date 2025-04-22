import { FeatureFlags } from '@centreon/ui-context';
import {
  path,
  difference,
  equals,
  has,
  includes,
  isEmpty,
  isNil,
  pluck,
  reject,
  type,
  T
} from 'ramda';
import {
  FederatedWidgetOption,
  WidgetHiddenCondition
} from '../../../../../federatedModules/models';
import { FormikValues } from 'formik';

interface CheckHiddenConditionProps {
  hiddenCondition: WidgetHiddenCondition;
  hasModule: boolean;
  featureFlags: FeatureFlags | null;
  values: FormikValues;
}

const checkHiddenCondition = ({
  hiddenCondition,
  hasModule,
  featureFlags,
  values
}: CheckHiddenConditionProps): boolean => {
  const { target, method, when, matches } = hiddenCondition;
  console.log(method, when);

  if (equals(target, 'featureFlags')) {
    return hasModule && !equals(featureFlags?.[hiddenCondition.when], matches);
  }

  if (equals(method, 'includes')) {
    const formValue = path(when.split('.'), values);
    const property = hiddenCondition?.property;
    const items = property ? pluck(property, formValue) : formValue;
    const areItemsString = equals(type(items), 'String');

    return (
      hasModule &&
      (isEmpty(reject(equals(''), items)) ||
        (areItemsString
          ? !includes(items, matches)
          : !isEmpty(difference(reject(equals(''), items), matches))))
    );
  }

  if (equals(method, 'includes-only')) {
    const formValue = path(when.split('.'), values);
    const property = hiddenCondition?.property;
    const items = property ? pluck(property, formValue) : formValue;

    return (
      hasModule &&
      (isEmpty(reject(equals(''), items)) ||
        !equals(
          items.filter((v) => v),
          matches
        ))
    );
  }

  if (equals(method, 'isNil')) {
    const formValue = path(when.split('.'), values);

    return hasModule && !isEmpty(formValue) && !isNil(formValue);
  }

  return hasModule && !equals(path(when.split('.'), values), matches);
};

interface Props {
  widgetProperties: Record<string, FederatedWidgetOption>;
  modules;
  featureFlags: FeatureFlags | null;
  values: FormikValues;
}

export const handleHiddenConditions = ({
  widgetProperties,
  modules,
  featureFlags,
  values
}: Props) => {
  return Object.entries(widgetProperties).filter(([, value]) => {
    const hasModule = value.hasModule ? has(value.hasModule, modules) : true;

    if (!value.hiddenCondition) {
      return true;
    }

    if (equals(type(value.hiddenCondition), 'Array')) {
      // Applies a OR logical condition as the checkHiddenCondition. As it is a filter we have to use a every function.
      return (value.hiddenCondition as Array<WidgetHiddenCondition>).every(
        (hiddenCondition) =>
          checkHiddenCondition({
            hasModule,
            featureFlags,
            hiddenCondition,
            values
          })
      );
    }

    return checkHiddenCondition({
      hasModule,
      featureFlags,
      hiddenCondition: value.hiddenCondition as WidgetHiddenCondition,
      values
    });
  });
};
