export interface SearchInput {
  searchValue?: string;
  searchOptions?: Array<string>;
}

export interface SearchObject {
  field: string;
  value: string;
}

type SearchPatterns = Array<{ [field: string]: { $rg: string } }>;

export interface OrSearchParam {
  $or: SearchPatterns;
}

export interface AndSearchParam {
  $and: SearchPatterns;
}

interface Sort {
  [sortf: string]: string;
}

export interface ListingOptions {
  sort?: Sort;
  page?: number;
  limit?: number;
  search?: string;
  searchOptions?: Array<string>;
}

type Value = string | number | OrSearchParam | AndSearchParam | Sort;

export interface Param {
  name: string;
  value?: Value;
}
