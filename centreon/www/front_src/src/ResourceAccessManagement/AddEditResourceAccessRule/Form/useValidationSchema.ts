import { equals, isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';
import {
  array,
  ArraySchema,
  boolean,
  object,
  ObjectSchema,
  ObjectShape,
  string
} from 'yup';
import { useAtomValue } from 'jotai';

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

  const datasetFiltersSchema = (): ArraySchema<ArraySchema<ObjectSchema>> =>
    array(
      array(
        object({
          allOfResourceType: boolean(),
          resourceType: string().matches(
            /(host|service)(group|_category)?|meta_service|all/
          ),
          resources: array().when(
            ['allOfResourceType', 'resourceType'],
            ([allOfResourceType, resourceType], schema) => {
              const typesForAllOf = ['host', 'hostgroup', 'servicegroup'];

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
      allContactrGroups: boolean(),
      allContacts: boolean(),
      contactGroups: array().when(
        ['allContactGroups', 'allContacts', 'contacts'],
        ([allContactGroups, allContacts, contacts], schema) => {
          if (isEmpty(contacts) && (!allContacts || !allContactGroups)) {
            return schema.min(1);
          }

          if (!isEmpty(contacts)) {
            return schema;
          }

          return schema.min(0);
        }
      ),
      contacts: array().when(
        ['allContactGroups', 'allContacts', 'contactGroups'],
        ([allContactGroups, allContacts, contactGroups], schema) => {
          if (isEmpty(contactGroups) && (!allContactGroups || allContacts)) {
            return schema.min(1);
          }

          if (!isEmpty(contactGroups)) {
            return schema;
          }

          return schema.min(0);
        }
      ),
      datasetFilters: datasetFiltersSchema(),
      name: validateName
    },
    [['contacts', 'contactGroups']]
  );

  return { validationSchema };
};

export default useValidationSchema;
