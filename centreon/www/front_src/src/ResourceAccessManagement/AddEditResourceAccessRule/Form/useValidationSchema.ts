import { useAtomValue } from 'jotai';
import { equals, isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';
import {
  ArraySchema,
  ObjectSchema,
  ObjectShape,
  array,
  boolean,
  object,
  string
} from 'yup';

import {
  editedResourceAccessRuleIdAtom,
  resourceAccessRulesNamesAtom
} from '../../atom';
import { labelNameAlreadyExists, labelRequired } from '../../translatedLabels';

interface UseValidationSchemaState {
  validationSchema: ObjectSchema<ObjectShape>;
}

const useValidationSchema = (): UseValidationSchemaState => {
  const { t } = useTranslation();
  const ruleNames = useAtomValue(resourceAccessRulesNamesAtom);
  const ruleId = useAtomValue(editedResourceAccessRuleIdAtom);

  const names = ruleNames
    .filter((item) => item.id !== ruleId)
    .map((item) => item.name);

  const validateName = string()
    .required(t(labelRequired) as string)
    .notOneOf(names, t(labelNameAlreadyExists) as string);

  const validateContacts = (): ArraySchema<ObjectSchema> => {
    return array().when(
      ['allContactGroups', 'allContacts', 'contactGroups'],
      ([allContactGroups, allContacts, contactGroups], schema) => {
        if (isEmpty(contactGroups) && allContactGroups) {
          return schema.min(0);
        }

        if (isEmpty(contactGroups) && allContacts) {
          return schema.min(0);
        }

        if (!isEmpty(contactGroups)) {
          return schema.min(0);
        }

        return schema.min(1);
      }
    );
  };

  const validateContactGroups = (): ArraySchema<ObjectSchema> => {
    return array().when(
      ['allContactGroups', 'allContacts', 'contacts'],
      ([allContactGroups, allContacts, contacts], schema) => {
        if (isEmpty(contacts) && allContacts) {
          return schema.min(0);
        }

        if (isEmpty(contacts) && allContactGroups) {
          return schema.min(0);
        }

        if (!isEmpty(contacts)) {
          return schema.min(0);
        }

        return schema.min(1);
      }
    );
  };

  const datasetFiltersSchema = (): ArraySchema<ArraySchema<ObjectSchema>> =>
    array(
      array(
        object({
          allOfResourceType: boolean(),
          resourceType: string().matches(
            /(host|service)(group|_category)?|meta_service|business_view|all/
          ),
          resources: array().when(
            ['allOfResourceType', 'resourceType'],
            ([allOfResourceType, resourceType], schema) => {
              const typesForAllOf = [
                'business_view',
                'host',
                'hostgroup',
                'servicegroup'
              ];

              if (equals('all', resourceType)) {
                return schema.min(0);
              }

              if (typesForAllOf.includes(resourceType) && allOfResourceType) {
                return schema.min(0);
              }

              return schema.min(1);
            }
          )
        })
      ).min(1)
    ).min(1);

  const validationSchema = object().shape(
    {
      allContactGroups: boolean(),
      allContacts: boolean(),
      contactGroups: validateContactGroups(),
      contacts: validateContacts(),
      datasetFilters: datasetFiltersSchema(),
      name: validateName
    },
    [['contacts', 'contactGroups']]
  );

  return { validationSchema };
};

export default useValidationSchema;
