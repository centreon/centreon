export interface BuildListingEndpointParameters {
  baseEndpoint?: string;
  parameters: Parameters;
  customQueryParameters?: Array<QueryParameter>;
}

interface SortQueryParameterValue {
  [sortf: string]: string;
}

export interface RegexSearchParameter {
  value: string;
  fields: Array<string>;
}

export interface ListsSearchParameter {
  field: string;
  values: Array<string>;
}

export interface SearchMatch {
  field: string;
  value: string;
}

export interface Parameters {
  sort?: SortQueryParameterValue;
  page?: number;
  limit?: number;
  search?: SearchParameter;
  customQueryParameters?: Array<QueryParameter>;
}

type SearchPatterns = Array<{ [field: string]: { $rg: string } }>;

export interface OrSearchQueryParameterValue {
  $or: SearchPatterns;
}

export interface AndSearchQueryParameterValue {
  $and: SearchPatterns;
}

export type RegexSearchQueryParameterValue =
  | OrSearchQueryParameterValue
  | AndSearchQueryParameterValue
  | undefined;

export interface SearchParameter {
  regex?: RegexSearchParameter;
  lists?: Array<ListsSearchParameter>;
}

export interface ListsSearchQueryParameterValue {
  $and: Array<{ [field: string]: { [field: string]: { $in: Array<string> } } }>;
}

export type SearchQueryParameterValue =
  | {
      $and: Array<
        RegexSearchQueryParameterValue | ListsSearchQueryParameterValue
      >;
    }
  | RegexSearchQueryParameterValue
  | ListsSearchQueryParameterValue
  | undefined;

export type QueryParameterValue =
  | string
  | number
  | SortQueryParameterValue
  | SearchQueryParameterValue
  | Array<string>
  | undefined;

export interface QueryParameter {
  name: string;
  value: QueryParameterValue;
}
