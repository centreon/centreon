import { equals, isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';
import {
  array,
  ArraySchema,
  AnySchema,
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
import {
  labelChooseAtLeastOneContactOrContactGroup,
  labelNameAlreadyExists,
  labelRequired
} from '../../translatedLabels';

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

  const contactsSchema = (dependency: string): ArraySchema<AnySchema> =>
    array().when(dependency, ([value]) => {
      if (isEmpty(value)) {
        return array().min(
          1,
          t(labelChooseAtLeastOneContactOrContactGroup) as string
        );
      }

      return array();
    });

  const datasetFiltersSchema = (): ArraySchema<ArraySchema<ObjectSchema>> =>
    array(
      array(
        object({
          allOfresourceType: boolean(),
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
      contactGroups: contactsSchema('contacts'),
      contacts: contactsSchema('contactGroups'),
      datasetFilters: datasetFiltersSchema(),
      name: validateName
    },
    [['contacts', 'contactGroups']]
  );

  return { validationSchema };
};

export default useValidationSchema;
