import pluralize from 'pluralize';
import {
  __,
  allPass,
  compose,
  concat,
  endsWith,
  filter,
  find,
  flip,
  head,
  includes,
  invertObj,
  isEmpty,
  isNil,
  keys,
  last,
  map,
  not,
  partition,
  pipe,
  prop,
  propEq,
  propSatisfies,
  reject,
  slice,
  sortBy,
  split,
  startsWith,
  toLower,
  trim,
  without
} from 'ramda';

import type { SelectEntry } from '@centreon/ui';

import getDefaultCriterias from '../default';
import {
  type Criteria,
  type CriteriaDisplayProps,
  criteriaValueNameById,
  selectableCriterias
} from '../models';

import {
  type AutocompleteSuggestionProps,
  type CriteriaId,
  type CriteriaValueSuggestionsProps,
  criteriaNameSortOrder,
  criteriaNameToQueryLanguageName,
  dynamicCriteriaValuesByName,
  getSelectableCriteriasByName,
  searchableFields,
  staticCriteriaNames
} from './models';

const singular = pluralize.singular as (string) => string;

const isIn = flip(includes);

const getCriteriaQueryLanguageName = (name: string): string => {
  return criteriaNameToQueryLanguageName[name] || name;
};

const getCriteriaNameFromQueryLanguageName = (name: string): string => {
  return invertObj(criteriaNameToQueryLanguageName)[name] || name;
};

const selectableCriteriaNames = keys(selectableCriterias) as Array<string>;

const isCriteriaPart = pipe(
  split(':'),
  head,
  getCriteriaNameFromQueryLanguageName,
  pluralize,
  isIn(selectableCriteriaNames)
);
const isFilledCriteria = pipe(endsWith(':'), not);

const replaceEscapeWithSpace = (text) => {
  return text.replace(/\\s/g, ' ');
};

export const replaceMiddleSpace = (text: string) => {
  return text.replace(/\b\s+\b/g, '\\s');
};

interface ParametersParse {
  criteriaName?: Record<string, string>;
  search: string;
}

const parse = ({
  search,
  criteriaName = criteriaValueNameById
}: ParametersParse): Array<Criteria> => {
  const [criteriaParts, rawSearchParts] = partition(
    allPass([includes(':'), isCriteriaPart, isFilledCriteria]),
    search.split(' ')
  );

  const criterias: Array<Criteria> = criteriaParts.map((criteria) => {
    const [key, values] = criteria.split(':');
    const unmappedCriteriaKey = getCriteriaNameFromQueryLanguageName(key);
    const pluralizedKey = pluralize(unmappedCriteriaKey);

    const defaultCriteria = find(
      propEq(pluralizedKey, 'name'),
      getDefaultCriterias()
    );

    const objectType = defaultCriteria?.object_type || null;

    const result = {
      name: pluralizedKey,
      object_type: objectType,
      type: 'multi_select',
      value: values?.split(',').map((value, index) => {
        const isStaticCriteria = isNil(objectType);

        if (isStaticCriteria) {
          const id = getCriteriaNameFromQueryLanguageName(toLower(value));

          return {
            id,
            name: criteriaName[id]
          };
        }

        return {
          id: index,
          name: replaceEscapeWithSpace(value),
          formattedName: value
        };
      })
    };

    return result;
  });

  const criteriasWithSearch = [
    ...criterias,
    {
      name: 'search',
      object_type: null,
      type: 'text',
      value: rawSearchParts.join(' ').trim()
    }
  ];

  const toNames = map(prop('name'));
  const criteriaNames = toNames(criteriasWithSearch);

  const isInCriteriaNames = isIn(criteriaNames);

  const nameIsInCriteriaNames = pipe(({ name }) => name, isInCriteriaNames);

  const defaultCriterias = reject(nameIsInCriteriaNames, getDefaultCriterias());

  return sortBy(
    ({ name }) => criteriaNameSortOrder[name],
    reject(propEq('sort', 'name'), [
      ...defaultCriterias,
      ...criteriasWithSearch
    ])
  );
};

const build = (criterias: Array<Criteria>): string => {
  const nameEqualsSearch = propEq('search', 'name');
  const nameEqualsSort = propEq('sort', 'name');
  const hasEmptyValue = propSatisfies(isEmpty, 'value');

  const rejectSearch = reject(nameEqualsSearch);
  const rejectSort = reject(nameEqualsSort);
  const rejectEmpty = reject(hasEmptyValue);

  const search = find(nameEqualsSearch, criterias);
  const regularCriterias = pipe(
    rejectSearch,
    rejectSort,
    rejectEmpty
  )(criterias);

  const builtCriterias = regularCriterias
    .filter(pipe(({ value }) => value, isNil, not))
    .map(({ name, value, object_type }): string => {
      const values = value as Array<SelectEntry>;
      const isStaticCriteria = isNil(object_type);

      const formattedValues = isStaticCriteria
        ? values.map(
            pipe(({ id }) => id as string, getCriteriaQueryLanguageName)
          )
        : values.map(({ name: valueName }) => replaceMiddleSpace(valueName));

      const criteriaName = compose(
        getCriteriaQueryLanguageName,
        singular
      )(name);

      return `${criteriaName}:${formattedValues.join(',')}`;
    })
    .join(' ');

  if (isEmpty(builtCriterias.trim())) {
    return search?.value as string;
  }

  return [builtCriterias, search?.value].join(' ');
};

const getCriteriaNameSuggestions = (word: string): Array<string> => {
  const criteriaNames = map(
    compose(getCriteriaQueryLanguageName, pluralize.singular),
    selectableCriteriaNames
  );

  if (isEmpty(word)) {
    return [];
  }

  const suggestions = filter(startsWith(word), [
    ...criteriaNames,
    ...searchableFields
  ]);

  return map(concat(__, ':'), suggestions);
};

const getCriteriaValueSuggestions = ({
  selectedValues,
  criterias
}: CriteriaValueSuggestionsProps): Array<string> => {
  const criteriaNames = map<CriteriaId, string>(
    compose(getCriteriaQueryLanguageName, prop('id'))
  )(criterias);

  return without(selectedValues, criteriaNames);
};

const getIsNextCharacterEmpty = ({
  search,
  cursorPosition
}: AutocompleteSuggestionProps): boolean => {
  const nextCharacter = search[cursorPosition];

  return isNil(nextCharacter) || isEmpty(nextCharacter.trim());
};

interface CriteriaExpression {
  criteriaName: string;
  expressionBeforeCursor: string;
  expressionCriteriaValues: Array<string>;
}

const getCriteriaAndExpression = ({
  search,
  cursorPosition
}: AutocompleteSuggestionProps): CriteriaExpression => {
  const searchBeforeCursor = slice(0, cursorPosition + 1, search);
  const expressionBeforeCursor = pipe(
    trim,
    split(' '),
    last
  )(searchBeforeCursor) as string;

  const expressionCriteria = expressionBeforeCursor.split(':');
  const criteriaQueryLanguageName = head(expressionCriteria) as string;
  const criteriaName = getCriteriaNameFromQueryLanguageName(
    criteriaQueryLanguageName
  );
  const expressionCriteriaValues = pipe(last, split(','))(expressionCriteria);

  return {
    criteriaName,
    expressionBeforeCursor,
    expressionCriteriaValues
  };
};

interface DynamicCriteriaParametersAndValues {
  criteria: CriteriaDisplayProps;
  values: Array<string>;
}

const getDynamicCriteriaParametersAndValue = ({
  search,
  cursorPosition,
  newSelectableCriterias = selectableCriterias
}: AutocompleteSuggestionProps): DynamicCriteriaParametersAndValues | null => {
  const isNextCharacterEmpty = getIsNextCharacterEmpty({
    cursorPosition,
    search
  });

  if (isNil(cursorPosition) || !isNextCharacterEmpty) {
    return null;
  }

  const { criteriaName, expressionCriteriaValues } = getCriteriaAndExpression({
    cursorPosition,
    search
  });

  const pluralizedCriteriaName = pluralize(criteriaName);

  const hasCriteriaDynamicValues = includes(
    pluralizedCriteriaName,
    dynamicCriteriaValuesByName
  );

  return hasCriteriaDynamicValues
    ? {
        criteria: newSelectableCriterias[pluralizedCriteriaName],
        values: expressionCriteriaValues
      }
    : null;
};

const getAutocompleteSuggestions = ({
  search,
  cursorPosition,
  newSelectableCriterias
}: AutocompleteSuggestionProps): Array<string> => {
  const isNextCharacterEmpty = getIsNextCharacterEmpty({
    cursorPosition,
    search
  });

  if (isNil(cursorPosition) || !isNextCharacterEmpty) {
    return [];
  }

  const { criteriaName, expressionCriteriaValues, expressionBeforeCursor } =
    getCriteriaAndExpression({ cursorPosition, search });

  const hasCriteriaStaticValues = includes(criteriaName, staticCriteriaNames);

  if (isEmpty(criteriaName)) {
    return [];
  }

  if (includes(':', expressionBeforeCursor) && hasCriteriaStaticValues) {
    const criterias = getSelectableCriteriasByName(criteriaName);
    const lastCriteriaValue = last(expressionCriteriaValues) || '';

    const criteriaNames = pluralize(criteriaName);

    const criteriasForInstalledModules = newSelectableCriterias?.[criteriaNames]
      ?.options as Array<{ id: string; name: string }>;

    const allCriterias = newSelectableCriterias
      ? criteriasForInstalledModules
      : criterias;

    const criteriaValueSuggestions = getCriteriaValueSuggestions({
      criterias: allCriterias,
      selectedValues: map(toLower, expressionCriteriaValues)
    });

    const isLastValueInSuggestions = getCriteriaValueSuggestions({
      criterias: allCriterias,
      selectedValues: []
    }).includes(toLower(lastCriteriaValue));

    return isLastValueInSuggestions
      ? map(concat(','), criteriaValueSuggestions)
      : filter(
          startsWith(toLower(lastCriteriaValue)),
          criteriaValueSuggestions
        );
  }

  return reject(includes(__, search), getCriteriaNameSuggestions(criteriaName));
};

export {
  build,
  type DynamicCriteriaParametersAndValues,
  getAutocompleteSuggestions,
  getDynamicCriteriaParametersAndValue,
  parse,
  searchableFields
};
