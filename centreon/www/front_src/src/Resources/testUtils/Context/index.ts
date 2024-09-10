import { createContext } from 'react';

import { FilterState } from '../../Filter/useFilter';
import { ListingState } from '../../Listing/useListing';
import { ActionsState } from '../useActions';
import { DetailsState } from '../useLoadDetails';

export type ResourceContext = Partial<FilterState> &
  Partial<ActionsState> &
  Partial<DetailsState> &
  Partial<ListingState>;

const Context = createContext<ResourceContext | undefined>(undefined);

export default Context;
